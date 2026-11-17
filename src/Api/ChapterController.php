<?php

namespace Ernestdefoe\Anthology\Api;

use Carbon\Carbon;
use Ernestdefoe\Anthology\Access;
use Ernestdefoe\Anthology\Chapter;
use Ernestdefoe\Anthology\DiscussionSeries;
use Ernestdefoe\Anthology\Job\NotifyFollowers;
use Ernestdefoe\Anthology\Series;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Contracts\Queue\Queue;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/anthology/chapters {discussionId, seriesId} adds a discussion to a
 * series as its newest chapter (or moves it there from another series);
 * DELETE /api/anthology/chapters/{id} takes it out again.
 *
 * Either way the answer is what the discussion page shows, so the page
 * updates without reloading.
 */
class ChapterController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private DiscussionSeries $discussionSeries, private Queue $queue)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $this->assertCanView($actor);
        $removing = $request->getMethod() === 'DELETE';
        $data = $this->body($request);

        $discussion = Discussion::query()->whereVisibleTo($actor)
            ->findOrFail($removing ? $this->routeId($request) : $this->id($data['discussionId'] ?? ''));
        $current = Chapter::query()->find($discussion->id);
        $from = $current ? Series::query()->find($current->series_id) : null;

        if ($removing) {
            if ($current && $from) {
                if (! Access::canRemove($actor, $discussion, $from)) {
                    throw new PermissionDeniedException();
                }
                $current->delete();
            }

            return new JsonResponse(['data' => null]);
        }

        $series = Series::query()->findOrFail($this->id($data['seriesId'] ?? ''));
        if (! Access::canAdd($actor, $discussion, $series) || ($from && $from->id !== $series->id && ! Access::canRemove($actor, $discussion, $from))) {
            throw new PermissionDeniedException();
        }

        if (! $current || $current->series_id !== $series->id) {
            $chapter = $current ?? new Chapter();
            $chapter->discussion_id = $discussion->id;
            $chapter->series_id = $series->id;
            $chapter->position = (int) Chapter::query()->where('series_id', $series->id)->max('position') + 1;
            $chapter->created_at = Carbon::now();
            $chapter->save();

            $series->last_chapter_at = Carbon::now();
            $series->save();

            if ($series->follower_count > 0 && ! $discussion->hidden_at) {
                $this->queue->push(new NotifyFollowers($series->id, $discussion->id));
            }
        }

        return new JsonResponse(['data' => $this->discussionSeries->for($discussion, $actor)]);
    }
}
