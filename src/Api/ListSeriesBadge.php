<?php

namespace Ernestdefoe\Anthology\Api;

use Ernestdefoe\Anthology\Access;
use Flarum\Api\Context;
use Flarum\Api\Endpoint\Index;
use Flarum\Settings\SettingsRepositoryInterface;

/**
 * The discussion list: each chapter's series, for its badge beside the tags,
 * loaded for the whole page in one query (chapters joined to series) while the
 * badge is on and the reader may view series. The chapter is the listed
 * discussion itself, so the reader can already see it, and a series they can
 * open a chapter of is one they can see.
 */
class ListSeriesBadge
{
    public const SETTING = 'ernestdefoe-anthology.list_badge';

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function __invoke(Index $endpoint): Index
    {
        return $endpoint->eagerLoad(fn (array $included, Context $context) => $this->shows($context) ? ['anthologyChapterSeries'] : []);
    }

    public function shows(Context $context): bool
    {
        return (bool) $this->settings->get(self::SETTING) && Access::canView($context->getActor());
    }
}
