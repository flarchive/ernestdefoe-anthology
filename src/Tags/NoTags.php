<?php

namespace Ernestdefoe\Anthology\Tags;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/** Tags is off: the directory has no tag filter or menu. */
class NoTags implements SeriesTags
{
    public function filter(Builder $query, string $slug, User $actor): void
    {
    }

    public function menu(User $actor): ?array
    {
        return null;
    }
}
