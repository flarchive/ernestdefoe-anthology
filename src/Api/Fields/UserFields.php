<?php

namespace Ernestdefoe\Anthology\Api\Fields;

use Ernestdefoe\Anthology\Access;
use Ernestdefoe\Anthology\Series;
use Flarum\Api\Context;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\User\User;

/**
 * The count beside the profile's Series tab, on the profile only (never on user
 * lists), one count query: the series this reader can open. A series counts once
 * it has a chapter they can read, so an empty one (a draft) or one whose
 * chapters are all in a hidden tag is never counted or hinted at.
 */
class UserFields
{
    /** @return list<Schema\Integer> */
    public function __invoke(): array
    {
        return [
            Schema\Integer::make('anthologySeriesCount')
                ->visible(fn (User $user, Context $context) => $context->showing(UserResource::class) && Access::canView($context->getActor()))
                ->get(fn (User $user, Context $context) => Series::query()
                    ->where('user_id', $user->id)
                    ->whereHas('discussions', fn ($d) => $d->whereVisibleTo($context->getActor()))
                    ->count()),
        ];
    }
}
