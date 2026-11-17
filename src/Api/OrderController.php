<?php

namespace Ernestdefoe\Anthology\Api;

use Ernestdefoe\Anthology\Chapter;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PATCH /api/anthology/series/{id}/order {ids: [discussion ids]}: the owner's
 * new reading order. Chapters the list leaves out (ones the owner can't see,
 * say) keep their order after the ones it names. Only moved rows are written.
 */
class OrderController implements RequestHandlerInterface
{
    use Concerns;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $series = $this->editableSeries($actor, $this->routeId($request));
        $wanted = array_values(array_unique(array_filter(array_map(fn ($id) => $this->id($id), (array) ($this->body($request)['ids'] ?? [])))));

        $chapters = Chapter::query()->where('series_id', $series->id)->orderBy('position')->orderBy('discussion_id')->get()->keyBy('discussion_id');
        $order = array_merge(
            array_values(array_filter($wanted, fn ($id) => $chapters->has($id))),
            $chapters->keys()->diff($wanted)->values()->all()
        );

        $series->getConnection()->transaction(function () use ($order, $chapters) {
            foreach ($order as $i => $id) {
                $chapter = $chapters[$id];
                if ($chapter->position !== $i + 1) {
                    $chapter->position = $i + 1;
                    $chapter->save();
                }
            }
        });

        return new EmptyResponse(204);
    }
}
