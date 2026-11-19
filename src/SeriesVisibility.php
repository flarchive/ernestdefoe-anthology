<?php

namespace Ernestdefoe\Anthology;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who sees a series: everyone with the view permission once it has a chapter
 * they can open; its owner (while they may make series) and moderators also
 * while it is empty, so they can file discussions under it.
 */
class SeriesVisibility
{
    public function __invoke(User $actor, Builder $query): void
    {
        if (! Access::canView($actor)) {
            $query->whereKey([]);

            return;
        }
        if (Access::canModerate($actor)) {
            return;
        }

        $query->where(function (Builder $q) use ($actor) {
            $q->whereHas('discussions', fn ($d) => $d->whereVisibleTo($actor));
            if (Access::canCreate($actor)) {
                $q->orWhere('anthology_series.user_id', $actor->id);
            }
        });
    }
}
