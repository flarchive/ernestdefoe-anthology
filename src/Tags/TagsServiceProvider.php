<?php

namespace Ernestdefoe\Anthology\Tags;

use Flarum\Foundation\AbstractServiceProvider;

/** Registered only while Tags is enabled: swaps NoTags for the real filter and menu. */
class TagsServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(SeriesTags::class, TagsIntegration::class);
    }
}
