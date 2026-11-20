<?php

namespace Ernestdefoe\Anthology\Search\Filter;

use Ernestdefoe\Anthology\Series;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * `filter[status]=ongoing|complete`.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class StatusFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'status';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        /** @var DatabaseSearchState $state */
        $status = $this->asString($value);
        $state->getQuery()->where('anthology_series.status', $negate ? '!=' : '=', in_array($status, Series::STATUSES, true) ? $status : '');
    }
}
