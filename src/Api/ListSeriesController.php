<?php

namespace Ernestdefoe\Anthology\Api;

use Ernestdefoe\Anthology\Access;
use Ernestdefoe\Anthology\Chapter;
use Ernestdefoe\Anthology\Presenter;
use Ernestdefoe\Anthology\Series;
use Flarum\Extension\ExtensionManager;
use Flarum\Http\RequestUtil;
use Flarum\Tags\Tag;
use Flarum\User\User;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/anthology/series — the directory, a member's series (filter[user])
 * and the actor's own (filter[mine], for the "Add to a series" picker).
 * With Tags on, filter[tag] keeps series with a chapter in that tag, and
 * `tags=1` adds the tags that have one (one more query) for the directory's
 * tag menu.
 *
 * Three queries whatever the page holds: the series with a count of the
 * chapters this reader may see, their authors, and nothing per series. Other
 * people's series show only once they have a chapter the reader can open.
 */
class ListSeriesController implements RequestHandlerInterface
{
    use Concerns;

    public const PER_PAGE = 24;

    public function __construct(private Presenter $presenter, private ExtensionManager $extensions)
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

        $tags = $this->extensions->isEnabled('flarum-tags');
        $tag = $tags ? trim((string) ($filter['tag'] ?? '')) : '';
        if ($tag !== '') {
            // A hidden tag's discussions aren't visible, so its slug matches nothing.
            $query->whereHas('discussions', fn ($d) => $visible($d)->whereHas('tags', fn ($t) => $t->where('tags.slug', mb_substr($tag, 0, 100))));
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

        $meta = ['next' => $more ? $offset + self::PER_PAGE : null];
        if ($tags && ! empty($params['tags'])) {
            $meta['tags'] = $this->tags($actor);
        }

        return new JsonResponse([
            'data' => $rows->take(self::PER_PAGE)->map(fn (Series $s) => $this->presenter->card($s, $actor))->values()->all(),
            'meta' => $meta,
        ]);
    }

    /**
     * The tags with a chapter this reader can open, primary tags in their
     * order first, then the rest by name.
     *
     * @return list<array<string, mixed>>
     */
    private function tags(User $actor): array
    {
        return Tag::whereVisibleTo($actor)
            ->whereHas('discussions', fn ($d) => $d->whereVisibleTo($actor)->whereIn('discussions.id', Chapter::query()->select('discussion_id')))
            ->get(['tags.id', 'tags.name', 'tags.slug', 'tags.color', 'tags.icon', 'tags.position'])
            ->sortBy([fn ($a, $b) => ($a->position ?? PHP_INT_MAX) <=> ($b->position ?? PHP_INT_MAX), fn ($a, $b) => strnatcasecmp($a->name, $b->name)])
            ->map(fn (Tag $t) => ['id' => $t->id, 'slug' => $t->slug, 'name' => $t->name, 'color' => $t->color, 'icon' => $t->icon])
            ->values()
            ->all();
    }
}
