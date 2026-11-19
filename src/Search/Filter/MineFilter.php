<?php

namespace Ernestdefoe\Anthology\Search\Filter;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * `filter[mine]=1`: the reader's own series (for the "Add to a series" picker). Nothing for a guest.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class MineFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'mine';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        /** @var DatabaseSearchState $state */
        $state->getQuery()->where('anthology_series.user_id', $negate ? '!=' : '=', (int) $state->getActor()->id);
    }
}
