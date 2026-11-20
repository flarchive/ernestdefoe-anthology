<?php

namespace Ernestdefoe\Anthology\Policy;

use Ernestdefoe\Anthology\Access;
use Ernestdefoe\Anthology\Series;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

/** Editing and deleting a series: its owner while they may make series, or a moderator (Access::canEdit). */
class SeriesPolicy extends AbstractPolicy
{
    public function update(User $actor, Series $series): bool
    {
        return Access::canView($actor) && Access::canEdit($actor, $series);
    }

    public function delete(User $actor, Series $series): bool
    {
        return $this->update($actor, $series);
    }
}
