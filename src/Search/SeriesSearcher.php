<?php

namespace Ernestdefoe\Anthology\Search;

use Ernestdefoe\Anthology\Series;
use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/** The series this reader may see, each with a count of the chapters they can open. */
class SeriesSearcher extends AbstractSearcher
{
    /** @return Builder<Series> */
    public function getQuery(User $actor): Builder
    {
        return Series::query()
            ->select('anthology_series.*')
            ->whereVisibleTo($actor)
            ->withCount(['discussions as chapter_count' => fn ($d) => $d->whereVisibleTo($actor)]);
    }
}
