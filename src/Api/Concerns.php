<?php

namespace Ernestdefoe\Anthology\Api;

use Ernestdefoe\Anthology\Access;
use Ernestdefoe\Anthology\Series;
use Flarum\Foundation\ValidationException;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

/** The lookups every controller starts with. */
trait Concerns
{
    /** The id in the route, or 0 (not found) for one too big for an id column. */
    protected function routeId(ServerRequestInterface $request, string $name = 'id'): int
    {
        return $this->id(Arr::get($request->getAttributes(), 'routeParameters.'.$name));
    }

    protected function id(mixed $value): int
    {
        $value = (string) (is_scalar($value) ? $value : '');

        return ctype_digit($value) && strlen($value) <= 10 && (int) $value <= 2147483647 ? (int) $value : 0;
    }

    /** @return array<string, mixed> */
    protected function body(ServerRequestInterface $request): array
    {
        return (array) Arr::get((array) $request->getParsedBody(), 'data', []);
    }

    protected function assertCanView(User $actor): void
    {
        if (! Access::canView($actor)) {
            throw new PermissionDeniedException();
        }
    }

    /**
     * A series the actor may open: one with at least one chapter they can
     * read, or their own. A 404 otherwise, the same answer as a missing one,
     * so a series of chapters in a hidden tag gives nothing away.
     */
    protected function visibleSeries(User $actor, int $id): Series
    {
        $this->assertCanView($actor);
        $series = Series::query()->with('user')->findOrFail($id);

        if (! Access::canEdit($actor, $series) && ! $series->discussions()->whereVisibleTo($actor)->exists()) {
            throw new ModelNotFoundException();
        }

        return $series;
    }

    protected function editableSeries(User $actor, int $id): Series
    {
        $series = Series::query()->with('user')->findOrFail($id);

        if (! Access::canView($actor) || ! Access::canEdit($actor, $series)) {
            throw new PermissionDeniedException();
        }

        return $series;
    }

    /** @param array<string, string> $params */
    protected function invalid(string $field, string $key, array $params = []): never
    {
        throw new ValidationException([$field => resolve('translator')->trans('ernestdefoe-anthology.api.'.$key, $params)]);
    }
}
