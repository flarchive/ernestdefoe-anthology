<?php

namespace Ernestdefoe\Anthology\Api\Fields;

use Ernestdefoe\Anthology\Access;
use Ernestdefoe\Anthology\Api\ListSeriesBadge;
use Ernestdefoe\Anthology\Chapters;
use Ernestdefoe\Anthology\DiscussionSeries;
use Flarum\Api\Context;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;

/**
 * Which series a chapter is in, on the discussion page (two queries) and, as a
 * badge, in the discussion list (ListSeriesBadge, one query for the page), and `anthologySeriesId` to file it under one (null takes it out).
 * Chapters checks who may; the answer to that write (only that one, not every
 * discussion update) carries the updated `anthologySeries`.
 */
class DiscussionFields
{
    public function __construct(private DiscussionSeries $discussionSeries, private Chapters $chapters, private ListSeriesBadge $badge)
    {
    }

    /** Whether this update writes `anthologySeriesId`. */
    private static function filing(Context $context): bool
    {
        return array_key_exists('anthologySeriesId', (array) ($context->body()['data']['attributes'] ?? []));
    }

    /** @return list<Schema\Attribute> */
    public function __invoke(): array
    {
        return [
            Schema\Arr::make('anthologySeries')
                ->nullable()
                ->visible(fn (Discussion $d, Context $context) => Access::canView($context->getActor())
                    && ($context->showing(DiscussionResource::class) || ($context->updating(DiscussionResource::class) && self::filing($context))))
                ->get(fn (Discussion $d, Context $context) => $this->discussionSeries->for($d, $context->getActor())),
            // The discussion list's badge: from the relation ListSeriesBadge eager-loads for the page, never a query of its own.
            Schema\Arr::make('anthologySeriesBadge')
                ->nullable()
                ->visible(fn (Discussion $d, Context $context) => $context->listing(DiscussionResource::class) && $this->badge->shows($context))
                ->get(fn (Discussion $d) => ($series = $d->relationLoaded('anthologyChapterSeries') ? $d->getRelation('anthologyChapterSeries') : null)
                    ? ['id' => $series->id, 'slug' => $series->slug, 'title' => $series->title]
                    : null),
            Schema\Integer::make('anthologySeriesId')
                ->nullable()
                ->visible(false)
                ->writable(fn (Discussion $d, Context $context) => $d->exists && ! $context->getActor()->isGuest() && Access::canView($context->getActor()))
                ->save(fn (Discussion $d, $value, Context $context) => $this->chapters->file($d, $value ? (int) $value : null, $context->getActor())),
        ];
    }
}
