<?php

namespace Ernestdefoe\Anthology\Api;

use Carbon\Carbon;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/anthology/series/{id}/follow and DELETE to stop. Followers are told about each new chapter. */
class FollowController implements RequestHandlerInterface
{
    use Concerns;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        if ($actor->isGuest()) {
            throw new PermissionDeniedException();
        }

        $series = $this->visibleSeries($actor, $this->routeId($request));
        $follows = $series->getConnection()->table('anthology_follows');
        $mine = fn () => $follows->newQuery()->from('anthology_follows')->where('series_id', $series->id)->where('user_id', $actor->id);

        if ($request->getMethod() === 'DELETE') {
            if ($mine()->delete()) {
                $series->newQuery()->whereKey($series->id)->where('follower_count', '>', 0)->decrement('follower_count');
            }
            $following = false;
        } else {
            if ($series->user_id === $actor->id) {
                throw new PermissionDeniedException();
            }
            if ($follows->insertOrIgnore(['series_id' => $series->id, 'user_id' => $actor->id, 'created_at' => Carbon::now()])) {
                $series->newQuery()->whereKey($series->id)->increment('follower_count');
            }
            $following = true;
        }

        return new JsonResponse(['data' => [
            'following' => $following,
            'followerCount' => (int) $series->newQuery()->whereKey($series->id)->value('follower_count'),
        ]]);
    }
}
