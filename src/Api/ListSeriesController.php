<?php

namespace Ernestdefoe\Anthology\Api;

use Ernestdefoe\Anthology\Access;
use Ernestdefoe\Anthology\Presenter;
use Ernestdefoe\Anthology\Series;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/anthology/series — the directory, a member's series (filter[user])
 * and the actor's own (filter[mine], for the "Add to a series" picker).
 *
 * Three queries whatever the page holds: the series with a count of the
 * chapters this reader may see, their authors, and nothing per series. Other
 * people's series show only once they have a chapter the reader can open.
 */
class ListSeriesController implements RequestHandlerInterface
{
    use Concerns;

    public const PER_PAGE = 24;

    public function __construct(private Presenter $presenter)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $this->assertCanView($actor);

        $params = $request->getQueryParams();
        $filter = (array) ($params['filter'] ?? []);
        $offset = max(0, min(100000, (int) (((array) ($params['page'] ?? []))['offset'] ?? 0)));
        $visible = fn ($d) => $d->whereVisibleTo($actor);

        $query = Series::query()->with('user')->withCount(['discussions as chapter_count' => $visible]);

        $user = ! empty($filter['mine']) ? $actor->id : $this->id($filter['user'] ?? '');
        if (! empty($filter['mine']) && $actor->isGuest()) {
            return new JsonResponse(['data' => [], 'meta' => ['next' => null]]);
        }
        if ($user) {
            $query->where('anthology_series.user_id', $user);
        }
        // A moderator sees a member's empty series too, so they can file that member's discussions under it.
        if (! $user || ($user !== $actor->id && ! Access::canModerate($actor))) {
            $query->whereHas('discussions', $visible);
        }

        $status = (string) ($filter['status'] ?? '');
        if (in_array($status, Series::STATUSES, true)) {
            $query->where('anthology_series.status', $status);
        }

        $q = trim((string) ($filter['q'] ?? ''));
        if ($q !== '') {
            $query->where('anthology_series.title', 'like', '%'.addcslashes(mb_substr($q, 0, 100), '\\%_').'%');
        }

        match ((string) ($params['sort'] ?? '')) {
            'new' => $query->orderByDesc('anthology_series.id'),
            'popular' => $query->orderByDesc('anthology_series.follower_count')->orderByDesc('anthology_series.last_chapter_at'),
            'title' => $query->orderBy('anthology_series.title'),
            default => $query->orderByDesc('anthology_series.last_chapter_at')->orderByDesc('anthology_series.id'),
        };

        $rows = $query->skip($offset)->take(self::PER_PAGE + 1)->get();
        $more = $rows->count() > self::PER_PAGE;

        return new JsonResponse([
            'data' => $rows->take(self::PER_PAGE)->map(fn (Series $s) => $this->presenter->card($s, $actor))->values()->all(),
            'meta' => ['next' => $more ? $offset + self::PER_PAGE : null],
        ]);
    }
}
