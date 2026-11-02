<?php

namespace Ernestdefoe\Anthology\Content;

use Ernestdefoe\Anthology\Access;
use Ernestdefoe\Anthology\Covers;
use Ernestdefoe\Anthology\Series;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * A series page's title, description, cover and chapter list in the HTML
 * itself, for link previews, search engines and readers without JavaScript.
 * Two queries, on this page only, with the same visibility as the API.
 */
class SeriesDocument
{
    public function __construct(private UrlGenerator $url, private Covers $covers)
    {
    }

    public function __invoke(Document $document, Request $request): Document
    {
        $id = (int) Arr::get($request->getQueryParams(), 'id');
        $actor = RequestUtil::getActor($request);
        if ($id < 1 || $id > 2147483647 || ! Access::canView($actor)) {
            return $document;
        }

        $series = Series::query()->find($id);
        $chapters = $series?->discussions()->whereVisibleTo($actor)->get(['discussions.id', 'discussions.title', 'discussions.slug']);
        if (! $series || ! $chapters || $chapters->isEmpty()) {
            return $document;
        }

        $summary = Str::limit(trim((string) preg_replace('/\s+/', ' ', (string) $series->description)), 200);
        $url = $this->url->to('forum')->route('anthology.series', ['id' => $series->id.($series->slug ? '-'.$series->slug : '')]);
        $cover = $this->covers->url($series);

        $document->title = $series->title;
        $document->canonicalUrl = $url;
        if ($summary !== '') {
            $document->meta['description'] = $summary;
        }
        $document->head[] = '<meta property="og:title" content="'.e($series->title).'">';
        $document->head[] = '<meta property="og:type" content="book">';
        $document->head[] = '<meta property="og:url" content="'.e($url).'">';
        if ($summary !== '') {
            $document->head[] = '<meta property="og:description" content="'.e($summary).'">';
        }
        if ($cover) {
            $document->head[] = '<meta property="og:image" content="'.e($cover).'">';
        }

        $list = $chapters->map(fn ($d) => '<li><a href="'.e($this->url->to('forum')->route('discussion', ['id' => $d->id.'-'.$d->slug])).'">'.e($d->title).'</a></li>')->implode('');
        $document->content = '<h1>'.e($series->title).'</h1>'.($summary !== '' ? '<p>'.e($summary).'</p>' : '').'<ol>'.$list.'</ol>';

        return $document;
    }
}
