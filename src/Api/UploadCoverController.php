<?php

namespace Ernestdefoe\Anthology\Api;

use Psr\Http\Message\ServerRequestInterface;

/** POST /api/anthology-series/{id}/cover (multipart `cover`): checked, re-encoded and stored by Covers. */
class UploadCoverController extends CoverController
{
    protected function cover(ServerRequestInterface $request): ?string
    {
        return $this->covers->store($request->getUploadedFiles()['cover'] ?? null);
    }
}
