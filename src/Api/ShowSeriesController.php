<?php

namespace Ernestdefoe\Anthology\Api;

use Ernestdefoe\Anthology\Access;
use Ernestdefoe\Anthology\Presenter;
use Ernestdefoe\Anthology\Series;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/anthology/series/{id} — the series page: the table of contents this
 * reader may see, which chapters they have read, and whether they follow it.
 *
 * "Read" is core's own record: a reader has read a chapter once Flarum has
 * stored how far they got in it. Nothing new is written while reading, and
 * chapters read before Anthology was installed count too.
 *
 * Five queries however long the series is.
 */
class ShowSeriesController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Presenter $presenter)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $this->assertCanView($actor);

        $series = Series::query()->with('user')->findOrFail($this->routeId($request));
        $chapters = $series->discussions()
            ->whereVisibleTo($actor)
            ->get(['discussions.id', 'discussions.title', 'discussions.slug', 'discussions.created_at', 'discussions.comment_count'])
            ->values();
        $canEdit = Access::canEdit($actor, $series);

        if ($chapters->isEmpty() && ! $canEdit) {
            throw new ModelNotFoundException();
        }

        $read = [];
        $following = false;
        if (! $actor->isGuest()) {
            if ($chapters->isNotEmpty()) {
                $read = array_flip($series->getConnection()->table('discussion_user')
                    ->where('user_id', $actor->id)
                    ->whereIn('discussion_id', $chapters->pluck('id')->all())
                    ->where('last_read_post_number', '>', 0)
                    ->pluck('discussion_id')
                    ->map(fn ($id) => (int) $id)
                    ->all());
            }
            $following = $series->user_id !== $actor->id
                && $series->getConnection()->table('anthology_follows')->where('series_id', $series->id)->where('user_id', $actor->id)->exists();
        }

        $series->setAttribute('chapter_count', $chapters->count());

        return new JsonResponse(['data' => $this->presenter->card($series, $actor) + [
            'description' => $series->description,
            'createdAt' => $series->created_at->toIso8601String(),
            'chapters' => $chapters->map(fn (Discussion $d) => [
                'id' => $d->id,
                'slug' => $d->slug,
                'title' => $d->title,
                'createdAt' => $d->created_at?->toIso8601String(),
                'replies' => max(0, (int) $d->comment_count - 1),
                'read' => isset($read[$d->id]),
            ])->all(),
            'following' => $following,
            'canFollow' => ! $actor->isGuest() && $series->user_id !== $actor->id,
            'canDelete' => $canEdit,
        ]]);
    }
}
