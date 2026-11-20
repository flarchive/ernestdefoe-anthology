<?php

namespace Ernestdefoe\Anthology;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\UserState;
use Flarum\User\User;

/**
 * A reader and a series: the table of contents they may see with what they
 * have read, and whether they follow it.
 *
 * "Read" is core's own record: a reader has read a chapter once Flarum has
 * stored how far they got in it. Nothing new is written while reading, and
 * chapters read before Anthology was installed count too.
 */
class Reading
{
    /**
     * Two queries however long the series is: the visible chapters, and which of them this reader has opened.
     *
     * @return list<array<string, mixed>>
     */
    public function chapters(Series $series, User $actor): array
    {
        $chapters = $series->discussions()
            ->whereVisibleTo($actor)
            ->get(['discussions.id', 'discussions.title', 'discussions.slug', 'discussions.created_at', 'discussions.comment_count'])
            ->values();

        $read = [];
        if (! $actor->isGuest() && $chapters->isNotEmpty()) {
            $read = array_flip(UserState::query()
                ->where('user_id', $actor->id)
                ->whereIn('discussion_id', $chapters->pluck('id')->all())
                ->where('last_read_post_number', '>', 0)
                ->pluck('discussion_id')
                ->map(fn ($id) => (int) $id)
                ->all());
        }

        return $chapters->map(fn (Discussion $d) => [
            'id' => $d->id,
            'slug' => $d->slug,
            'title' => $d->title,
            'createdAt' => $d->created_at?->toIso8601String(),
            'replies' => max(0, (int) $d->comment_count - 1),
            'read' => isset($read[$d->id]),
        ])->all();
    }

    public function follows(Series $series, User $actor): bool
    {
        return ! $actor->isGuest()
            && $series->user_id !== $actor->id
            && Follow::query()->where('series_id', $series->id)->where('user_id', $actor->id)->exists();
    }

    /** Followers are told about each new chapter. The count moves only when a row was really added or removed. */
    public function follow(Series $series, User $actor, bool $on): void
    {
        $changed = $on
            ? Follow::query()->insertOrIgnore(['series_id' => $series->id, 'user_id' => $actor->id, 'created_at' => Carbon::now()])
            : Follow::query()->where('series_id', $series->id)->where('user_id', $actor->id)->delete();

        if ($changed) {
            $count = Follow::query()->where('series_id', $series->id)->count();
            $series->newQuery()->whereKey($series->id)->update(['follower_count' => $count]);
            $series->setAttribute('follower_count', $count)->syncOriginalAttribute('follower_count');
        }
    }
}
