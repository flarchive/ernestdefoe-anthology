<?php

namespace Ernestdefoe\Anthology\Tests\integration\api;

use Ernestdefoe\Anthology\Series;
use Ernestdefoe\Anthology\Tests\integration\AnthologyTestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class SeriesTest extends AnthologyTestCase
{
    /**
     * Core mails each recipient in their own queued job, which reloads the
     * user and issues an unsubscribe token. The test queue is synchronous, so
     * those per-recipient jobs land inside the request.
     */
    protected function allowedRepeatedQueries(): array
    {
        return ['unsubscribe_tokens', 'users"."id" = ? limit ?', 'users`.`id` = ? limit ?'];
    }

    #[Test]
    public function members_start_series_and_guests_cannot(): void
    {
        $series = $this->series();
        $this->assertSame('the-long-road', $series['slug']);
        $this->assertSame('ongoing', $series['status']);
        $this->assertSame(1, User::query()->find(2)->anthology_series_count);

        [$status] = $this->json('POST', '/api/anthology/series', null, ['data' => ['title' => 'Nope']]);
        $this->assertContains($status, [401, 403]);

        [$status] = $this->json('POST', '/api/anthology/series', 2, ['data' => ['title' => '   ']]);
        $this->assertSame(422, $status);
    }

    #[Test]
    public function only_the_author_adds_their_own_discussion_to_their_own_series(): void
    {
        $mine = $this->series();
        $carols = $this->series('Carol Writes', 3);

        $this->add(4, $mine['id'], 2, 403);         // carol's discussion into normal's series
        $this->add(1, $carols['id'], 2, 403);       // normal's discussion into carol's series
        $this->add(1, $mine['id'], 3, 403);         // carol filing normal's discussion

        $data = $this->add(1, $mine['id']);
        $this->assertSame(1, $data['number']);
        $this->assertSame(1, $data['count']);

        // A moderator may file anyone's discussion anywhere.
        $this->add(4, $mine['id'], 4);
    }

    #[Test]
    public function a_moderator_starts_a_series_for_a_member_and_files_their_discussions_under_it(): void
    {
        // Only a moderator may start one in someone else's name.
        [$status] = $this->json('POST', '/api/anthology/series', 3, ['data' => ['title' => 'Not Yours', 'userId' => 2]]);
        $this->assertSame(403, $status);

        [$status, $body, $raw] = $this->json('POST', '/api/anthology/series', 4, ['data' => ['title' => 'Old Novel', 'userId' => 2]]);
        $this->assertSame(201, $status, $raw);
        $this->assertSame(2, Series::query()->find($body['data']['id'])->user_id);
        $this->assertSame(1, User::query()->find(2)->anthology_series_count);
        $this->assertSame(0, (int) User::query()->find(4)->anthology_series_count);

        // The moderator's picker lists the member's series while it is still empty; a reader's directory doesn't.
        [, $list] = $this->json('GET', '/api/anthology/series', 4, null, ['filter' => ['user' => '2']]);
        $this->assertSame(['Old Novel'], array_column($list['data'], 'title'));
        [, $list] = $this->json('GET', '/api/anthology/series', 3, null, ['filter' => ['user' => '2']]);
        $this->assertSame([], $list['data']);

        $this->add(1, $body['data']['id'], 4);
        $this->add(2, $body['data']['id'], 4);

        // It is the member's own: they edit it and add to it.
        [$status] = $this->json('PATCH', '/api/anthology/series/'.$body['data']['id'], 2, ['data' => ['status' => 'complete']]);
        $this->assertSame(200, $status);
        $this->assertSame(3, $this->add(3, $body['data']['id'])['number']);
    }

    #[Test]
    public function the_discussion_page_shows_the_chapter_and_its_neighbours_but_lists_do_not(): void
    {
        $series = $this->series();
        foreach ([1, 2, 3] as $id) {
            $this->add($id, $series['id']);
        }

        [, $body] = $this->json('GET', '/api/discussions/2', 3);
        $chapter = $body['data']['attributes']['anthologySeries'];
        $this->assertSame(2, $chapter['number']);
        $this->assertSame(3, $chapter['count']);
        $this->assertSame(1, $chapter['prev']['id']);
        $this->assertSame(3, $chapter['next']['id']);

        [, $list] = $this->json('GET', '/api/discussions', 3);
        foreach ($list['data'] as $discussion) {
            $this->assertArrayNotHasKey('anthologySeries', $discussion['attributes']);
        }

        // Not in a series: null on its page.
        [, $body] = $this->json('GET', '/api/discussions/4', 3);
        $this->assertNull($body['data']['attributes']['anthologySeries']);
    }

    #[Test]
    public function a_chapter_in_a_tag_the_reader_cannot_see_is_never_listed_counted_or_linked(): void
    {
        $series = $this->series();
        $this->add(1, $series['id']);
        $this->add(5, $series['id'], 2, 404);  // the Secret tag: normal can't even see it...
        $this->add(5, $series['id'], 1); // ...so the admin files it.
        $this->add(2, $series['id']);

        [, $body] = $this->json('GET', '/api/anthology/series/'.$series['id'], 3);
        $this->assertSame([1, 2], array_column($body['data']['chapters'], 'id'));
        $this->assertSame(2, $body['data']['chapterCount']);
        $this->assertStringNotContainsString('Chapter 5', json_encode($body));

        [, $body] = $this->json('GET', '/api/discussions/1', 3);
        $this->assertSame(2, $body['data']['attributes']['anthologySeries']['next']['id']);

        [, $body] = $this->json('GET', '/api/anthology/series/'.$series['id'], 1);
        $this->assertSame([1, 5, 2], array_column($body['data']['chapters'], 'id'));
    }

    #[Test]
    public function a_series_of_hidden_chapters_is_missing_for_readers_and_out_of_the_directory(): void
    {
        $series = $this->series('Secret Saga');
        $this->add(5, $series['id'], 1);

        [$status] = $this->json('GET', '/api/anthology/series/'.$series['id'], 3);
        $this->assertSame(404, $status);
        [, $list] = $this->json('GET', '/api/anthology/series', 3);
        $this->assertSame([], $list['data']);

        // Its owner still sees it among their own.
        [, $mine] = $this->json('GET', '/api/anthology/series', 2, null, ['filter' => ['mine' => '1']]);
        $this->assertSame([$series['id']], array_column($mine['data'], 'id'));
        $this->assertSame(0, $mine['data'][0]['chapterCount']);

        [, $list] = $this->json('GET', '/api/anthology/series', 1);
        $this->assertSame([$series['id']], array_column($list['data'], 'id'));
    }

    #[Test]
    public function reading_progress_comes_from_what_flarum_already_records(): void
    {
        $series = $this->series();
        $this->add(1, $series['id']);
        $this->add(2, $series['id']);
        $this->database()->table('discussion_user')->insert(['user_id' => 3, 'discussion_id' => 1, 'last_read_post_number' => 1]);

        [, $body] = $this->json('GET', '/api/anthology/series/'.$series['id'], 3);
        $this->assertSame([true, false], array_column($body['data']['chapters'], 'read'));
    }

    #[Test]
    public function the_owner_reorders_and_removes_chapters_and_others_cannot(): void
    {
        $series = $this->series();
        foreach ([1, 2, 3] as $id) {
            $this->add($id, $series['id']);
        }

        [$status] = $this->json('PATCH', '/api/anthology/series/'.$series['id'].'/order', 3, ['data' => ['ids' => [3, 2, 1]]]);
        $this->assertSame(403, $status);

        [$status] = $this->json('PATCH', '/api/anthology/series/'.$series['id'].'/order', 2, ['data' => ['ids' => [3, 1]]]);
        $this->assertSame(204, $status);
        [, $body] = $this->json('GET', '/api/anthology/series/'.$series['id'], 2);
        $this->assertSame([3, 1, 2], array_column($body['data']['chapters'], 'id'));

        [$status] = $this->json('DELETE', '/api/anthology/chapters/1', 3);
        $this->assertSame(403, $status);
        [$status] = $this->json('DELETE', '/api/anthology/chapters/1', 2);
        $this->assertSame(200, $status);
        [, $body] = $this->json('GET', '/api/anthology/series/'.$series['id'], 2);
        $this->assertSame([3, 2], array_column($body['data']['chapters'], 'id'));
    }

    #[Test]
    public function a_discussion_moves_between_its_authors_series(): void
    {
        $first = $this->series('One');
        $second = $this->series('Two');
        $this->add(1, $first['id']);
        $data = $this->add(1, $second['id']);
        $this->assertSame($second['id'], $data['id']);
        $this->assertSame(0, $this->database()->table('anthology_chapters')->where('series_id', $first['id'])->count());
    }

    #[Test]
    public function followers_hear_of_new_chapters_they_can_read_and_the_author_does_not(): void
    {
        $series = $this->series();
        $this->add(1, $series['id']);

        [$status, $body] = $this->json('POST', '/api/anthology/series/'.$series['id'].'/follow', 3);
        $this->assertSame(200, $status);
        $this->assertTrue($body['data']['following']);
        $this->assertSame(1, $body['data']['followerCount']);
        $this->json('POST', '/api/anthology/series/'.$series['id'].'/follow', 3); // twice is still once

        [$status] = $this->json('POST', '/api/anthology/series/'.$series['id'].'/follow', 2);
        $this->assertSame(403, $status); // your own

        $this->add(2, $series['id']);
        $this->add(5, $series['id'], 1);  // hidden from carol

        $sent = $this->database()->table('notifications')->where('type', 'anthologyNewChapter')->get();
        $this->assertSame([[3, 2]], $sent->map(fn ($n) => [(int) $n->user_id, (int) $n->subject_id])->all());
        $this->assertSame(['seriesId' => $series['id'], 'seriesTitle' => 'The Long Road'], json_decode($sent[0]->data, true));

        [, $body] = $this->json('DELETE', '/api/anthology/series/'.$series['id'].'/follow', 3);
        $this->assertSame(0, $body['data']['followerCount']);
    }

    #[Test]
    public function a_members_only_chapter_reaches_every_member_who_follows_with_one_check_per_set_of_groups(): void
    {
        $members = [];
        $follows = [];
        foreach (range(6, 25) as $id) {
            $members[] = ['id' => $id, 'username' => "reader$id", 'email' => "reader$id@machine.local", 'is_email_confirmed' => $id !== 25];
        }
        $this->prepareDatabase([
            User::class => $members,
            \Flarum\Tags\Tag::class => [['id' => 3, 'name' => 'Members', 'slug' => 'members', 'position' => 2, 'is_restricted' => 1]],
            'group_permission' => [['group_id' => \Flarum\Group\Group::MEMBER_ID, 'permission' => 'tag3.viewForum']],
            \Flarum\Discussion\Discussion::class => [['id' => 6, 'title' => 'Chapter 6', 'slug' => 'chapter-6', 'created_at' => \Carbon\Carbon::now(), 'last_posted_at' => \Carbon\Carbon::now(), 'user_id' => 2, 'first_post_id' => 6, 'comment_count' => 1, 'is_private' => 0]],
            \Flarum\Post\Post::class => [['id' => 6, 'discussion_id' => 6, 'created_at' => \Carbon\Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Members only</p></t>', 'number' => 1]],
            'discussion_tag' => [['discussion_id' => 6, 'tag_id' => 3]],
        ]);

        $series = $this->series();
        $this->add(1, $series['id']);
        foreach (array_merge([3, 4], range(6, 25)) as $id) {
            $follows[] = ['series_id' => $series['id'], 'user_id' => $id, 'created_at' => \Carbon\Carbon::now()];
        }
        $this->database()->table('anthology_follows')->insert($follows);
        $this->database()->table('anthology_series')->where('id', $series['id'])->update(['follower_count' => count($follows)]);

        $db = $this->database();
        $db->enableQueryLog();
        $this->add(6, $series['id']);
        $checks = array_filter($db->getQueryLog(), fn ($q) => str_starts_with($q['query'], 'select exists'));
        $db->disableQueryLog();

        // Everyone confirmed is told, the unconfirmed reader25 (no more than a guest) is not.
        $sent = $this->database()->table('notifications')->where('type', 'anthologyNewChapter')->where('subject_id', 6)->pluck('user_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $this->assertSame(array_merge([3, 4], range(6, 24)), $sent);

        // Guests, then members, moderators and the unconfirmed set, then reader25 alone: not one per follower.
        $this->assertCount(5, $checks);
    }

    #[Test]
    public function deleting_a_series_keeps_its_discussions(): void
    {
        $series = $this->series();
        $this->add(1, $series['id']);

        [$status] = $this->json('DELETE', '/api/anthology/series/'.$series['id'], 3);
        $this->assertSame(403, $status);
        [$status] = $this->json('DELETE', '/api/anthology/series/'.$series['id'], 2);
        $this->assertSame(204, $status);

        $this->assertSame(0, $this->database()->table('anthology_chapters')->count());
        [$status] = $this->json('GET', '/api/discussions/1', 3);
        $this->assertSame(200, $status);
        $this->assertSame(0, User::query()->find(2)->anthology_series_count);
    }

    #[Test]
    public function the_profile_tab_count_and_forum_permissions_are_sent(): void
    {
        $this->series();
        [, $body] = $this->json('GET', '/api/users/2', 3);
        $this->assertSame(1, $body['data']['attributes']['anthologySeriesCount']);

        [, $forum] = $this->json('GET', '/api', 3);
        $this->assertTrue($forum['data']['attributes']['canViewSeries']);
        $this->assertTrue($forum['data']['attributes']['canCreateSeries']);
        $this->assertFalse($forum['data']['attributes']['canModerateSeries']);
        $this->assertSame('series', $forum['data']['attributes']['anthologyNoun']);
    }
}
