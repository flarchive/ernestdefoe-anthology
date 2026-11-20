<?php

namespace Ernestdefoe\Anthology\Tags;

use Flarum\Foundation\AbstractServiceProvider;

/** Registered only while Tags is disabled: the directory has no tag filter or menu. */
class NoTagsServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(SeriesTags::class, NoTags::class);
    }
}
