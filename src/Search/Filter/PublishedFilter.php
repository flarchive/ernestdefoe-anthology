<?php

namespace Ernestdefoe\Anthology\Search\Filter;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * `filter[published]=1`: only series with a chapter this reader can open (the directory: an empty series stays off it even for its owner and moderators).
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class PublishedFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'published';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        /** @var DatabaseSearchState $state */
        $actor = $state->getActor();
        $state->getQuery()->{$negate ? 'whereDoesntHave' : 'whereHas'}('discussions', fn ($d) => $d->whereVisibleTo($actor));
    }
}
