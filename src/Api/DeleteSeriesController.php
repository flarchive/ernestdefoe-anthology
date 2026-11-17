<?php

namespace Ernestdefoe\Anthology\Api;

use Ernestdefoe\Anthology\Covers;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** DELETE /api/anthology/series/{id}. The discussions stay; only the grouping, cover and follows go. */
class DeleteSeriesController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Covers $covers)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $series = $this->editableSeries($actor, $this->routeId($request));
        $cover = $series->cover_path;

        $series->delete();
        $this->covers->delete($cover);

        if ($series->user_id) {
            User::query()->whereKey($series->user_id)->where('anthology_series_count', '>', 0)->decrement('anthology_series_count');
        }

        return new EmptyResponse(204);
    }
}
