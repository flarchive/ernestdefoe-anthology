<?php

namespace Ernestdefoe\Anthology\Job;

use Ernestdefoe\Anthology\Notification\NewChapterBlueprint;
use Ernestdefoe\Anthology\Series;
use Flarum\Discussion\Discussion;
use Flarum\Notification\NotificationSyncer;
use Flarum\Queue\AbstractJob;
use Flarum\User\Guest;
use Flarum\User\User;

/**
 * Tells a series' followers about a new chapter, in the queue when the forum
 * has one.
 *
 * Only followers who may read the chapter are told. A chapter a guest can read
 * is readable by every member (members hold every guest permission), so the
 * common case is one check, not one per follower.
 */
class NotifyFollowers extends AbstractJob
{
    public function __construct(private int $seriesId, private int $discussionId)
    {
    }

    public function handle(NotificationSyncer $notifications): void
    {
        $series = Series::query()->find($this->seriesId);
        $discussion = Discussion::query()->with('user')->find($this->discussionId);
        if (! $series || ! $discussion || $discussion->hidden_at) {
            return;
        }

        $followers = $series->followers()->where('users.id', '!=', (int) $discussion->user_id)->get();
        if ($followers->isEmpty()) {
            return;
        }

        $public = Discussion::query()->whereVisibleTo(new Guest())->whereKey($discussion->id)->exists();
        if (! $public) {
            $followers = $followers->filter(fn (User $user) => Discussion::query()->whereVisibleTo($user)->whereKey($discussion->id)->exists());
        }

        $notifications->sync(new NewChapterBlueprint($discussion, $series), $followers->values()->all());
    }
}
