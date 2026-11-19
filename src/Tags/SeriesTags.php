<?php

namespace Ernestdefoe\Anthology\Tags;

use Ernestdefoe\Anthology\Series;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The directory's tag filter and tag menu. Without Tags there is nothing to
 * filter by: NoTags. With Tags enabled, extend.php binds TagsIntegration in its
 * place through Extend\Conditional, so nothing here loads a Tags class otherwise.
 */
interface SeriesTags
{
    /** @param Builder<Series> $query */
    public function filter(Builder $query, string $slug, User $actor): void;

    /** @return list<array<string, mixed>>|null The tags with a chapter this reader can open, or null without Tags. */
    public function menu(User $actor): ?array;
}
