<?php

namespace Ernestdefoe\Anthology;

use Flarum\Discussion\Discussion;
use Flarum\User\User;

/**
 * Who may do what. Every rule reads permissions already loaded with the actor's
 * groups, so none of them queries.
 *
 * A member edits their own series and adds only discussions they started, so
 * nobody can pull someone else's thread into their book. Moderators can do
 * both for anyone.
 */
class Access
{
    public static function canView(User $actor): bool
    {
        return $actor->hasPermission('anthology.view');
    }

    public static function canCreate(User $actor): bool
    {
        return ! $actor->isGuest() && $actor->hasPermission('anthology.create');
    }

    public static function canModerate(User $actor): bool
    {
        return ! $actor->isGuest() && $actor->hasPermission('anthology.moderate');
    }

    /** The owner edits while they may still make series (a suspension or a removed permission stops it). */
    public static function canEdit(User $actor, Series $series): bool
    {
        return self::canModerate($actor) || (self::canCreate($actor) && $series->user_id === $actor->id);
    }

    public static function canAdd(User $actor, Discussion $discussion, Series $series): bool
    {
        if (self::canModerate($actor)) {
            return true;
        }

        return self::canCreate($actor)
            && $series->user_id === $actor->id
            && (int) $discussion->user_id === $actor->id;
    }

    /** Taking a chapter out: the series' owner, the discussion's author (always) or a moderator. */
    public static function canRemove(User $actor, Discussion $discussion, Series $series): bool
    {
        return self::canEdit($actor, $series) || (! $actor->isGuest() && (int) $discussion->user_id === $actor->id);
    }
}
