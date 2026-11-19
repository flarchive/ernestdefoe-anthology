<?php

namespace Ernestdefoe\Anthology\Search\Filter;

use Ernestdefoe\Anthology\Tags\SeriesTags;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * `filter[tag]=<slug>`: series with a chapter this reader can open in that tag. Without Tags it matches nothing to filter by (NoTags).
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class TagFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function __construct(private SeriesTags $tags)
    {
    }

    public function getFilterKey(): string
    {
        return 'tag';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        /** @var DatabaseSearchState $state */
        $apply = fn ($query) => $this->tags->filter($query, $this->asString($value), $state->getActor());
        $negate ? $state->getQuery()->whereNot($apply) : $apply($state->getQuery());
    }
}
