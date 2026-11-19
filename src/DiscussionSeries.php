<?php

namespace Ernestdefoe\Anthology;

use Flarum\Discussion\Discussion;
use Flarum\User\User;

/**
 * What a chapter's discussion page shows: its series, which chapter it is and
 * the chapters either side. Two queries, on the discussion page only: the
 * series row, and the chapters this reader may see (so "Chapter 3 of 15" and
 * the previous and next links skip anything in a tag they can't read).
 */
class DiscussionSeries
{
    public function __construct(private Covers $covers)
    {
    }

    /** @return array<string, mixed>|null */
    public function for(Discussion $discussion, User $actor): ?array
    {
        $series = Series::query()
            ->select('anthology_series.*')
            ->join('anthology_chapters', 'anthology_chapters.series_id', '=', 'anthology_series.id')
            ->where('anthology_chapters.discussion_id', $discussion->id)
            ->first();

        if (! $series) {
            return null;
        }

        $chapters = $series->discussions()
            ->whereVisibleTo($actor)
            ->get(['discussions.id', 'discussions.title', 'discussions.slug'])
            ->values();

        $index = $chapters->search(fn (Discussion $d) => $d->id === $discussion->id);
        $ref = fn (?Discussion $d) => $d ? ['id' => $d->id, 'slug' => $d->slug, 'title' => $d->title] : null;

        return [
            'id' => $series->id,
            'slug' => $series->slug,
            'title' => $series->title,
            'coverUrl' => $this->covers->url($series),
            'number' => $index === false ? null : $index + 1,
            'count' => $chapters->count(),
            'prev' => $index === false || $index === 0 ? null : $ref($chapters[$index - 1]),
            'next' => $index === false ? null : $ref($chapters[$index + 1] ?? null),
            'canEdit' => Access::canEdit($actor, $series),
        ];
    }
}
