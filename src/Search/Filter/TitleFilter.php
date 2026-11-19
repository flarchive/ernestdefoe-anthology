<?php

namespace Ernestdefoe\Anthology\Search\Filter;

use Ernestdefoe\Anthology\SeriesWords;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * `filter[title]=…`: titles with a word starting with each word typed, in any
 * case ("glass orch" finds "The Glass Orchard"), on the word index (SeriesWords).
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class TitleFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function __construct(private SeriesWords $words)
    {
    }

    public function getFilterKey(): string
    {
        return 'title';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        /** @var DatabaseSearchState $state */
        $apply = fn ($query) => $this->words->filter($query, $this->asString($value));
        $negate ? $state->getQuery()->whereNot($apply) : $apply($state->getQuery());
    }
}
