<?php

namespace Ernestdefoe\Anthology\Api\Fields;

use Ernestdefoe\Anthology\Access;
use Flarum\Api\Context;
use Flarum\Api\Schema;

/** What the forum frontend may show: permissions already loaded with the actor's groups, so no query. */
class ForumFields
{
    /** @return list<Schema\Boolean> */
    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('canViewSeries')
                ->get(fn ($forum, Context $context) => Access::canView($context->getActor())),
            Schema\Boolean::make('canCreateSeries')
                ->get(fn ($forum, Context $context) => Access::canCreate($context->getActor())),
            Schema\Boolean::make('canModerateSeries')
                ->get(fn ($forum, Context $context) => Access::canModerate($context->getActor())),
        ];
    }
}
