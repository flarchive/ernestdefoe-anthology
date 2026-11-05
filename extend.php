<?php

/*
 * Anthology — group discussions into series.
 *
 * A series is a title, a cover and an ordered list of discussions, its
 * chapters. Every chapter stays an ordinary discussion; Anthology adds the
 * table of contents, "Chapter 3 of 15" with previous and next on each chapter,
 * and a notification to followers when a chapter is added. Whatever a reader
 * is shown about a series goes through discussion visibility, so a chapter in
 * a tag they can't read is never listed, counted or announced to them.
 */

use Ernestdefoe\Anthology\Access;
use Ernestdefoe\Anthology\Api;
use Ernestdefoe\Anthology\Content\SeriesDocument;
use Ernestdefoe\Anthology\DiscussionSeries;
use Ernestdefoe\Anthology\Notification\NewChapterBlueprint;
use Flarum\Api\Context;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\User\User;

$series = '{id:\d+(?:-[^/]*)?}';

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        // The directory, series page, profile tab and the modals are their own chunks.
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less')
        ->route('/series', 'anthology.index')
        ->route("/series/$series", 'anthology.series', SeriesDocument::class)
        ->route('/u/{username}/series', 'user.anthology'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\View())
        ->namespace('ernestdefoe-anthology', __DIR__.'/views'),

    (new Extend\Routes('api'))
        ->get('/anthology/series', 'anthology.series.index', Api\ListSeriesController::class)
        ->post('/anthology/series', 'anthology.series.create', Api\SaveSeriesController::class)
        ->get('/anthology/series/{id:[0-9]+}', 'anthology.series.show', Api\ShowSeriesController::class)
        ->patch('/anthology/series/{id:[0-9]+}', 'anthology.series.update', Api\SaveSeriesController::class)
        ->delete('/anthology/series/{id:[0-9]+}', 'anthology.series.delete', Api\DeleteSeriesController::class)
        ->post('/anthology/series/{id:[0-9]+}/cover', 'anthology.series.cover', Api\CoverController::class)
        ->delete('/anthology/series/{id:[0-9]+}/cover', 'anthology.series.cover.delete', Api\CoverController::class)
        ->post('/anthology/series/{id:[0-9]+}/follow', 'anthology.series.follow', Api\FollowController::class)
        ->delete('/anthology/series/{id:[0-9]+}/follow', 'anthology.series.unfollow', Api\FollowController::class)
        ->patch('/anthology/series/{id:[0-9]+}/order', 'anthology.series.order', Api\OrderController::class)
        ->post('/anthology/chapters', 'anthology.chapters.add', Api\ChapterController::class)
        ->delete('/anthology/chapters/{id:[0-9]+}', 'anthology.chapters.remove', Api\ChapterController::class),

    (new Extend\Notification())
        ->type(NewChapterBlueprint::class, ['alert', 'email']),

    (new Extend\Model(User::class))
        ->cast('anthology_series_count', 'integer'),

    (new Extend\Settings())
        ->default('ernestdefoe-anthology.noun', 'series')
        ->default('ernestdefoe-anthology.show_nav', true)
        ->serializeToForum('anthologyNoun', 'ernestdefoe-anthology.noun', fn ($v) => in_array($v, ['series', 'novel', 'story', 'book'], true) ? $v : 'series')
        ->serializeToForum('anthologyShowNav', 'ernestdefoe-anthology.show_nav', 'boolval'),

    // Permissions already loaded with the actor's groups: no query.
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canViewSeries')
                ->get(fn ($forum, Context $context) => Access::canView($context->getActor())),
            Schema\Boolean::make('canCreateSeries')
                ->get(fn ($forum, Context $context) => Access::canCreate($context->getActor())),
            Schema\Boolean::make('canModerateSeries')
                ->get(fn ($forum, Context $context) => Access::canModerate($context->getActor())),
        ]),

    // A column on the user row: the profile tab shows only when there is something in it.
    (new Extend\ApiResource(UserResource::class))
        ->fields(fn () => [
            Schema\Integer::make('anthologySeriesCount')
                ->get(fn (User $user) => (int) $user->anthology_series_count),
        ]),

    // The discussion page only, never lists: which series this chapter is in.
    (new Extend\ApiResource(DiscussionResource::class))
        ->fields(fn () => [
            Schema\Arr::make('anthologySeries')
                ->nullable()
                ->visible(fn (Discussion $d, Context $context) => $context->showing(DiscussionResource::class) && Access::canView($context->getActor()))
                ->get(fn (Discussion $d, Context $context) => resolve(DiscussionSeries::class)->for($d, $context->getActor())),
        ]),
];
