<?php

namespace Ernestdefoe\Anthology;

use Carbon\Carbon;
use Ernestdefoe\Anthology\Job\NotifyFollowers;
use Flarum\Discussion\Discussion;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Contracts\Queue\Queue;

/**
 * Filing a discussion under a series as its newest chapter (or moving it there
 * from another series), and taking it out again. Written through the
 * discussion's own API resource (`anthologySeriesId`), so the answer is the
 * discussion the page already shows.
 */
class Chapters
{
    public function __construct(private Queue $queue)
    {
    }

    public function file(Discussion $discussion, ?int $seriesId, User $actor): void
    {
        $current = Chapter::query()->find($discussion->id);
        $from = $current ? Series::query()->find($current->series_id) : null;

        if (! $seriesId) {
            if ($current && $from) {
                if (! Access::canRemove($actor, $discussion, $from)) {
                    throw new PermissionDeniedException();
                }
                $current->delete();
            }

            return;
        }

        $series = Series::query()->findOrFail($seriesId);
        if (! Access::canAdd($actor, $discussion, $series) || ($from && $from->id !== $series->id && ! Access::canRemove($actor, $discussion, $from))) {
            throw new PermissionDeniedException();
        }

        if ($current && $current->series_id === $series->id) {
            return;
        }

        $chapter = $current ?? new Chapter();
        $chapter->discussion_id = $discussion->id;
        $chapter->series_id = $series->id;
        // Two chapters added at the same moment may share a position: reading order then falls back to the discussion id.
        $chapter->position = (int) Chapter::query()->where('series_id', $series->id)->max('position') + 1;
        $chapter->created_at = Carbon::now();
        $chapter->save();

        $series->last_chapter_at = Carbon::now();
        $series->save();

        if ($series->follower_count > 0 && ! $discussion->hidden_at) {
            $this->queue->push(new NotifyFollowers($series->id, $discussion->id));
        }
    }
}
