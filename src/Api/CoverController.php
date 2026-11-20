<?php

namespace Ernestdefoe\Anthology\Api;

use Ernestdefoe\Anthology\Access;
use Ernestdefoe\Anthology\Covers;
use Ernestdefoe\Anthology\Series;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A series cover: UploadCoverController and RemoveCoverController. Their own
 * handlers because an upload is multipart, not a JSON:API document; the rest
 * of a series is SeriesResource.
 */
abstract class CoverController implements RequestHandlerInterface
{
    public function __construct(protected Covers $covers)
    {
    }

    /** The new cover path (null: none), for a series the actor may edit. */
    abstract protected function cover(ServerRequestInterface $request): ?string;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $series = Series::query()->findOrFail((int) Arr::get($request->getAttributes(), 'routeParameters.id'));
        if (! Access::canView($actor) || ! Access::canEdit($actor, $series)) {
            throw new PermissionDeniedException();
        }

        $old = $series->cover_path;
        $series->cover_path = $this->cover($request);
        $series->save();

        if ($old && $old !== $series->cover_path) {
            $this->covers->delete($old);
        }

        return new JsonResponse(['data' => ['coverUrl' => $this->covers->url($series)]]);
    }
}
