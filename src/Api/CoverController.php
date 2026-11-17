<?php

namespace Ernestdefoe\Anthology\Api;

use Ernestdefoe\Anthology\Covers;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/anthology/series/{id}/cover (multipart `cover`) and DELETE to remove it. */
class CoverController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Covers $covers)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $series = $this->editableSeries($actor, $this->routeId($request));
        $old = $series->cover_path;

        if ($request->getMethod() === 'DELETE') {
            $series->cover_path = null;
        } else {
            $file = $request->getUploadedFiles()['cover'] ?? null;
            if (! $file instanceof UploadedFileInterface) {
                $this->invalid('cover', 'upload_failed');
            }
            $series->cover_path = $this->covers->store($file);
        }

        $series->save();
        if ($old && $old !== $series->cover_path) {
            $this->covers->delete($old);
        }

        return new JsonResponse(['data' => ['coverUrl' => $this->covers->url($series)]]);
    }
}
