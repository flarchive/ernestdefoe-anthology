<?php

namespace Ernestdefoe\Anthology\Tags;

use Ernestdefoe\Anthology\Chapter;
use Flarum\Tags\Tag;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/** Bound only while Tags is enabled (extend.php). */
class TagsIntegration implements SeriesTags
{
    /** Series with a chapter this reader can open in that tag. A hidden tag's discussions aren't visible, so its slug matches nothing. */
    public function filter(Builder $query, string $slug, User $actor): void
    {
        $query->whereHas('discussions', fn ($d) => $d->whereVisibleTo($actor)->whereHas('tags', fn ($t) => $t->where('tags.slug', mb_substr($slug, 0, 100))));
    }

    /** One query: the tags with a chapter this reader can open, primary tags in their order first, then the rest by name. */
    public function menu(User $actor): array
    {
        return Tag::whereVisibleTo($actor)
            ->whereHas('discussions', fn ($d) => $d->whereVisibleTo($actor)->whereIn('discussions.id', Chapter::query()->select('discussion_id')))
            ->get(['tags.id', 'tags.name', 'tags.slug', 'tags.color', 'tags.icon', 'tags.position'])
            ->sortBy([fn ($a, $b) => ($a->position ?? PHP_INT_MAX) <=> ($b->position ?? PHP_INT_MAX), fn ($a, $b) => strnatcasecmp($a->name, $b->name)])
            ->map(fn (Tag $t) => ['id' => $t->id, 'slug' => $t->slug, 'name' => $t->name, 'color' => $t->color, 'icon' => $t->icon])
            ->values()
            ->all();
    }
}
