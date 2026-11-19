<?php

namespace Ernestdefoe\Anthology\Api;

use Psr\Http\Message\ServerRequestInterface;

/** DELETE /api/anthology-series/{id}/cover. */
class RemoveCoverController extends CoverController
{
    protected function cover(ServerRequestInterface $request): ?string
    {
        return null;
    }
}
