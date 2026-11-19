<?php

namespace Ernestdefoe\Anthology\Job;

use Ernestdefoe\Anthology\Notification\NewChapterBlueprint;
use Ernestdefoe\Anthology\Series;
use Flarum\Discussion\Discussion;
use Flarum\Group\Group;
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
 * a stand-in drawing its permissions from exactly the follower's permission
 * groups (after every group processor, so a suspended member counts as the
 * guest flarum/suspend makes them) is readable by that follower. So the check
 * runs once for guests, then once per set of permission groups among the
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
        $ids = array_values(array_unique($user->permissionGroupIds()));
        sort($ids);
        $key = implode(',', $ids);

        if (! isset($byGroups[$key])) {
            // Nobody has id 0, so nothing granted to one person reaches it. Its groups are the follower's own
            // permission groups; if a group processor would make the stand-in's set differ, it answers nothing
            // and the follower is checked on their own.
            $standIn = new User();
            $standIn->id = 0;
            $standIn->is_email_confirmed = in_array(Group::MEMBER_ID, $ids, true);
            $standIn->setRelation('groups', $user->groups->filter(fn (Group $g) => in_array($g->id, $ids, true))->values());

            $same = array_values(array_unique($standIn->permissionGroupIds()));
            sort($same);
            $byGroups[$key] = $same === $ids && $this->readableBy($standIn, $discussion);
        }

        return $byGroups[$key];
    }

    private function readableBy(User $user, Discussion $discussion): bool
    {
        return Discussion::query()->whereVisibleTo($user)->whereKey($discussion->id)->exists();
    }
}
