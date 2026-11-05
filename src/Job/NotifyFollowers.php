<?php

namespace Ernestdefoe\Anthology\Job;

use Ernestdefoe\Anthology\Notification\NewChapterBlueprint;
use Ernestdefoe\Anthology\Series;
use Flarum\Discussion\Discussion;
use Flarum\Notification\NotificationSyncer;
use Flarum\Queue\AbstractJob;
use Flarum\User\Guest;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tells a series' followers about a new chapter, in the queue when the forum
 * has one.
 *
 * Only followers who may read the chapter are told. Permissions only add up:
 * a chapter a guest can read is readable by every member, and one readable by
 * a stand-in holding a follower's exact groups is readable by that follower.
 * So the check runs once for guests, then once per set of groups among the
 * followers (a members-only tag is one check, however many follow), and per
 * follower only for those whose groups alone don't open it (a private
 * discussion they were invited to, say).
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

        $public = $this->readableBy(new Guest(), $discussion);
        $byGroups = [];
        /** @var User[] $readers */
        $readers = [];

        // In chunks, so a large following is checked in bounded batches. The
        // recipients still go to sync() in one call: it withdraws this
        // notification from anyone left off the list.
        $series->followers()
            ->with('groups')
            ->where('users.id', '!=', (int) $discussion->user_id)
            ->chunkById(200, function (Collection $followers) use ($discussion, $public, &$byGroups, &$readers) {
                foreach ($followers as $user) {
                    /** @var User $user */
                    if ($public || $this->readableByGroupsOf($user, $discussion, $byGroups) || $this->readableBy($user, $discussion)) {
                        $readers[] = $user;
                    }
                }
            }, 'users.id', 'id');

        if ($readers) {
            $notifications->sync(new NewChapterBlueprint($discussion, $series), $readers);
        }
    }

    /** @param array<string, bool> $byGroups */
    private function readableByGroupsOf(User $user, Discussion $discussion, array &$byGroups): bool
    {
        $key = ($user->is_email_confirmed ? 'c:' : 'u:').collect($user->groups->modelKeys())->sort()->implode(',');

        if (! isset($byGroups[$key])) {
            // Nobody has id 0, so nothing granted to one person reaches it.
            $standIn = new User();
            $standIn->id = 0;
            $standIn->is_email_confirmed = $user->is_email_confirmed;
            $standIn->setRelation('groups', $user->groups);

            $byGroups[$key] = $this->readableBy($standIn, $discussion);
        }

        return $byGroups[$key];
    }

    private function readableBy(User $user, Discussion $discussion): bool
    {
        return Discussion::query()->whereVisibleTo($user)->whereKey($discussion->id)->exists();
    }
}
