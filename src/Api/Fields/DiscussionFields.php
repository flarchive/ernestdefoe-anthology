<?php

namespace Ernestdefoe\Anthology\Api\Fields;

use Ernestdefoe\Anthology\Access;
use Ernestdefoe\Anthology\Chapters;
use Ernestdefoe\Anthology\DiscussionSeries;
use Flarum\Api\Context;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;

/**
 * Which series a chapter is in, on the discussion page only (never lists, two
 * queries), and `anthologySeriesId` to file it under one (null takes it out).
 * Chapters checks who may; the answer to that write (only that one, not every
 * discussion update) carries the updated `anthologySeries`.
 */
class DiscussionFields
{
    public function __construct(private DiscussionSeries $discussionSeries, private Chapters $chapters)
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
            Schema\Integer::make('anthologySeriesId')
                ->nullable()
                ->visible(false)
                ->writable(fn (Discussion $d, Context $context) => $d->exists && ! $context->getActor()->isGuest() && Access::canView($context->getActor()))
                ->save(fn (Discussion $d, $value, Context $context) => $this->chapters->file($d, $value ? (int) $value : null, $context->getActor())),
        ];
    }
}
