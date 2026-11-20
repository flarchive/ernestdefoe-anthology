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

use Ernestdefoe\Anthology\SeriesVisibility;
use Ernestdefoe\Anthology\Api;
use Ernestdefoe\Anthology\Chapter;
use Ernestdefoe\Anthology\Content\SeriesDocument;
use Ernestdefoe\Anthology\Notification\NewChapterBlueprint;
use Ernestdefoe\Anthology\Policy;
use Ernestdefoe\Anthology\Search;
use Ernestdefoe\Anthology\Series;
use Ernestdefoe\Anthology\Tags;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Search\Database\DatabaseSearchDriver;

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

    // Series are a JSON:API resource. Only the cover upload, being multipart, has its own handler.
    new Extend\ApiResource(Api\SeriesResource::class),

    (new Extend\Routes('api'))
        ->post('/anthology-series/{id:[0-9]+}/cover', 'anthology.series.cover', Api\UploadCoverController::class)
        ->delete('/anthology-series/{id:[0-9]+}/cover', 'anthology.series.cover.delete', Api\RemoveCoverController::class),

    (new Extend\Policy())
        ->modelPolicy(Series::class, Policy\SeriesPolicy::class),

    (new Extend\ModelVisibility(Series::class))
        ->scope(SeriesVisibility::class),

    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addSearcher(Series::class, Search\SeriesSearcher::class)
        ->addFilter(Search\SeriesSearcher::class, Search\Filter\UserFilter::class)
        ->addFilter(Search\SeriesSearcher::class, Search\Filter\MineFilter::class)
        ->addFilter(Search\SeriesSearcher::class, Search\Filter\PublishedFilter::class)
        ->addFilter(Search\SeriesSearcher::class, Search\Filter\StatusFilter::class)
        ->addFilter(Search\SeriesSearcher::class, Search\Filter\TitleFilter::class)
        ->addFilter(Search\SeriesSearcher::class, Search\Filter\TagFilter::class),

    (new Extend\Notification())
        ->type(NewChapterBlueprint::class, ['alert', 'email']),

    (new Extend\Settings())
        ->default('ernestdefoe-anthology.noun', 'series')
        ->default('ernestdefoe-anthology.show_nav', true)
        ->default('ernestdefoe-anthology.list_badge', true)
        ->serializeToForum('anthologyNoun', 'ernestdefoe-anthology.noun', fn ($v) => in_array($v, ['series', 'novel', 'story', 'book'], true) ? $v : 'series')
        ->serializeToForum('anthologyShowNav', 'ernestdefoe-anthology.show_nav', 'boolval'),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(Api\Fields\ForumFields::class),

    (new Extend\ApiResource(UserResource::class))
        ->fields(Api\Fields\UserFields::class),

    (new Extend\ApiResource(DiscussionResource::class))
        ->fields(Api\Fields\DiscussionFields::class)
        ->endpoint(Endpoint\Index::class, Api\ListSeriesBadge::class),

    // A chapter's series in one joined query (anthology_chapters → anthology_series), for the list badge.
    (new Extend\Model(Discussion::class))
        ->relationship('anthologyChapterSeries', fn (Discussion $d) => $d->hasOneThrough(Series::class, Chapter::class, 'discussion_id', 'id', 'id', 'series_id')),

    // The directory's tag filter and menu exist only while Tags is enabled.
    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-tags', fn () => [(new Extend\ServiceProvider())->register(Tags\TagsServiceProvider::class)])
        ->whenExtensionDisabled('flarum-tags', fn () => [(new Extend\ServiceProvider())->register(Tags\NoTagsServiceProvider::class)]),
];
