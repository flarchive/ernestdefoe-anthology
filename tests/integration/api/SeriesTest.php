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

    /** The message the API answers with (the test app has no locale catalogue for extensions, so it may be the key). */
    private function trans(string $key): string
    {
        return $this->app()->getContainer()->make('translator')->trans('ernestdefoe-anthology.api.'.$key);
    }

    #[Test]
    public function members_start_series_and_guests_cannot(): void
    {
        $series = $this->series();
        $this->assertSame('the-long-road', $series['slug']);
        $this->assertSame('ongoing', $series['status']);

        [$status] = $this->createSeries(null, ['title' => 'Nope']);
        $this->assertContains($status, [401, 403]);

        [$status, , $raw] = $this->patchSeries($series['id'], 2, ['status' => 'bogus']);
        $this->assertSame(422, $status, $raw);
        $this->assertSame($this->trans('bad_status'), json_decode($raw, true)['errors'][0]['detail']);
        [$status, , $raw] = $this->createSeries(2, ['title' => '   ']);
        $this->assertSame(422, $status, $raw);
        $this->assertSame('/data/attributes/title', json_decode($raw, true)['errors'][0]['source']['pointer']);
        [$status, , $raw] = $this->createSeries(2, ['title' => str_repeat('x', 201)]);
        $this->assertSame(422, $status, $raw);
        $this->assertSame($this->trans('title_too_long'), json_decode($raw, true)['errors'][0]['detail']);
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
        [$status] = $this->createSeries(3, ['title' => 'Not Yours'], 2);
        $this->assertSame(403, $status);
        $this->assertSame(0, Series::query()->count());

        [$status, $novel, $raw] = $this->createSeries(4, ['title' => 'Old Novel'], 2);
        $this->assertSame(201, $status, $raw);
        $this->assertSame(2, Series::query()->find($novel['id'])->user_id);

        // The moderator's picker lists the member's series while it is still empty; a reader's list doesn't.
        $this->assertSame(['Old Novel'], array_column($this->listSeries(4, ['filter' => ['user' => '2']])[0], 'title'));
        $this->assertSame([], $this->listSeries(3, ['filter' => ['user' => '2']])[0]);

        $this->add(1, $novel['id'], 4);
        $this->add(2, $novel['id'], 4);

        // It is the member's own: they edit it and add to it.
        [$status, , $raw] = $this->patchSeries($novel['id'], 2, ['status' => 'complete']);
        $this->assertSame(200, $status, $raw);
        $this->assertSame(3, $this->add(3, $novel['id'])['number']);
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

        [, $body] = $this->showSeries($series['id'], 3);
        $this->assertSame([1, 2], array_column($body['chapters'], 'id'));
        $this->assertSame(2, $body['chapterCount']);
        $this->assertStringNotContainsString('Chapter 5', json_encode($body));

        [, $body] = $this->json('GET', '/api/discussions/1', 3);
        $this->assertSame(2, $body['data']['attributes']['anthologySeries']['next']['id']);

        [, $body] = $this->showSeries($series['id'], 1);
        $this->assertSame([1, 5, 2], array_column($body['chapters'], 'id'));
    }

    #[Test]
    public function a_series_of_hidden_chapters_is_missing_for_readers_and_out_of_the_directory(): void
    {
        $series = $this->series('Secret Saga');
        $this->add(5, $series['id'], 1);

        [$status] = $this->showSeries($series['id'], 3);
        $this->assertSame(404, $status);
        $this->assertSame([], $this->listSeries(3, ['filter' => ['published' => '1']])[0]);
        $this->assertSame([], $this->listSeries(3)[0]);
        $this->assertSame([], $this->listSeries(null)[0]);

        // Its owner still sees it among their own, but not in the directory.
        [$mine] = $this->listSeries(2, ['filter' => ['mine' => '1']]);
        $this->assertSame([$series['id']], array_column($mine, 'id'));
        $this->assertSame(0, $mine[0]['chapterCount']);
        $this->assertSame([], $this->listSeries(2, ['filter' => ['published' => '1']])[0]);

        $this->assertSame([$series['id']], array_column($this->listSeries(1, ['filter' => ['published' => '1']])[0], 'id'));
    }

    #[Test]
    public function reading_progress_comes_from_what_flarum_already_records(): void
    {
        $series = $this->series();
        $this->add(1, $series['id']);
        $this->add(2, $series['id']);
        $this->database()->table('discussion_user')->insert(['user_id' => 3, 'discussion_id' => 1, 'last_read_post_number' => 1]);

        [, $body] = $this->showSeries($series['id'], 3);
        $this->assertSame([true, false], array_column($body['chapters'], 'read'));
    }

    #[Test]
    public function the_owner_reorders_and_removes_chapters_and_others_cannot(): void
    {
        $series = $this->series();
        foreach ([1, 2, 3] as $id) {
            $this->add($id, $series['id']);
        }

        [$status] = $this->patchSeries($series['id'], 3, ['chapterOrder' => [3, 2, 1]]);
        $this->assertSame(403, $status);

        // One statement writes every moved row.
        $db = $this->database();
        $db->enableQueryLog();
        [$status, , $raw] = $this->patchSeries($series['id'], 2, ['chapterOrder' => [3, 1]]);
        $writes = array_filter($db->getQueryLog(), fn ($q) => stripos($q['query'], 'anthology_chapters') !== false && preg_match('/^(insert|update)/i', $q['query']));
        $db->disableQueryLog();
        $this->assertSame(200, $status, $raw);
        $this->assertCount(1, $writes);
        [, $body] = $this->showSeries($series['id'], 2);
        $this->assertSame([3, 1, 2], array_column($body['chapters'], 'id'));

        $this->add(1, null, 3, 403);
        $this->assertNull($this->add(1, null, 2));
        [, $body] = $this->showSeries($series['id'], 2);
        $this->assertSame([3, 2], array_column($body['chapters'], 'id'));
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

        [$status, $body, $raw] = $this->patchSeries($series['id'], 3, ['following' => true]);
        $this->assertSame(200, $status, $raw);
        $this->assertTrue($body['following']);
        $this->assertSame(1, $body['followerCount']);
        $this->assertSame(1, $this->patchSeries($series['id'], 3, ['following' => true])[1]['followerCount']); // twice is still once

        [$status] = $this->patchSeries($series['id'], 2, ['following' => true]);
        $this->assertSame(403, $status); // your own
        // A guest can't follow; nor can a reader edit what they follow.
        [$status] = $this->json('PATCH', '/api/anthology-series/'.$series['id'], null, ['data' => ['type' => 'anthology-series', 'id' => (string) $series['id'], 'attributes' => ['following' => true]]]);
        $this->assertContains($status, [401, 403]);
        [$status] = $this->patchSeries($series['id'], 3, ['title' => 'Mine now']);
        $this->assertSame(403, $status);

        $this->add(2, $series['id']);
        $this->add(5, $series['id'], 1);  // hidden from carol

        $sent = $this->database()->table('notifications')->where('type', 'anthologyNewChapter')->get();
        $this->assertSame([[3, 2]], $sent->map(fn ($n) => [(int) $n->user_id, (int) $n->subject_id])->all());
        $this->assertSame(['seriesId' => $series['id'], 'seriesTitle' => 'The Long Road'], json_decode($sent[0]->data, true));

        $this->assertSame(0, $this->patchSeries($series['id'], 3, ['following' => false])[1]['followerCount']);
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

        [$status] = $this->json('DELETE', '/api/anthology-series/'.$series['id'], 3);
        $this->assertSame(403, $status);
        [$status] = $this->json('DELETE', '/api/anthology-series/'.$series['id'], 2);
        $this->assertSame(204, $status);

        $this->assertSame(0, $this->database()->table('anthology_chapters')->count());
        $this->assertSame(0, $this->database()->table('anthology_series_words')->count());
        [$status] = $this->json('GET', '/api/discussions/1', 3);
        $this->assertSame(200, $status);
        // Nothing about series is kept on the core users table.
        $this->assertFalse($this->database()->getSchemaBuilder()->hasColumn('users', 'anthology_series_count'));
    }

    #[Test]
    public function the_profile_tab_count_and_forum_permissions_are_sent(): void
    {
        $this->add(1, $this->series()['id']);
        [, $body] = $this->json('GET', '/api/users/2', 3);
        $this->assertSame(1, $body['data']['attributes']['anthologySeriesCount']);

        [, $forum] = $this->json('GET', '/api', 3);
        $this->assertTrue($forum['data']['attributes']['canViewSeries']);
        $this->assertTrue($forum['data']['attributes']['canCreateSeries']);
        $this->assertFalse($forum['data']['attributes']['canModerateSeries']);
        $this->assertSame('series', $forum['data']['attributes']['anthologyNoun']);
    }

    #[Test]
    public function the_directory_filters_by_a_chapters_tag_and_lists_only_tags_in_use(): void
    {
        $this->database()->table('tags')->insert(['id' => 3, 'name' => 'Poetry', 'slug' => 'poetry']);
        $this->database()->table('tags')->insert(['id' => 4, 'name' => 'Empty', 'slug' => 'empty', 'position' => 2]);
        $this->database()->table('discussion_tag')->insert(['discussion_id' => 3, 'tag_id' => 3]);

        $road = $this->series();
        $this->add(1, $road['id']);
        $verse = $this->series('Verse');
        $this->add(3, $verse['id']);
        $secret = $this->series('Secret Saga', 1);
        $this->add(5, $secret['id'], 1);

        $ids = fn (string $tag, int $actor = 3) => array_column($this->listSeries($actor, ['filter' => ['tag' => $tag, 'published' => '1'], 'sort' => 'title'])[0], 'id');
        $this->assertSame([$verse['id']], $ids('poetry'));
        $this->assertSame([$road['id'], $verse['id']], $ids('stories'));
        $this->assertSame([], $ids('secret'));
        $this->assertSame([$secret['id']], $ids('secret', 1));
        $this->assertSame([], $ids('no-such-tag'));

        // The tag menu: tags with a chapter the reader can open, primary tags in order first.
        $tags = fn (int $actor) => array_column($this->listSeries($actor, ['withTags' => '1'])[1]['tags'], 'slug');
        $this->assertSame(['stories', 'poetry'], $tags(3));
        $this->assertSame(['stories', 'secret', 'poetry'], $tags(1));
        $this->assertArrayNotHasKey('tags', $this->listSeries(3)[1]);
    }

    #[Test]
    public function the_directory_searches_the_start_of_title_words_in_any_case_and_filters_by_status(): void
    {
        $road = $this->series('The Long Road');
        $this->add(1, $road['id']);
        $verse = $this->series('Verse 100%');
        $this->add(2, $verse['id']);
        $this->patchSeries($verse['id'], 2, ['status' => 'complete']);

        $titles = fn (array $filter) => array_column($this->listSeries(3, ['filter' => $filter + ['published' => '1'], 'sort' => 'title'])[0], 'title');
        $this->assertSame(['The Long Road'], $titles(['title' => 'long']));
        $this->assertSame(['The Long Road'], $titles(['title' => 'ROAD the']));
        $this->assertSame(['Verse 100%'], $titles(['title' => 'vers 10']));
        $this->assertSame([], $titles(['title' => 'oad']));       // the start of a word, not the middle
        $this->assertSame([], $titles(['title' => 'long verse'])); // every word typed must match
        $this->assertSame(['Verse 100%'], $titles(['status' => 'complete']));
        $this->assertSame(['The Long Road'], $titles(['status' => 'ongoing']));
        $this->assertSame(['The Long Road', 'Verse 100%'], $titles([]));
        $this->assertSame(['Verse 100%'], $titles(['-title' => 'long']));  // negated

        // A new title is searched by its new words only.
        $this->patchSeries($road['id'], 2, ['title' => 'Winter Orchard']);
        $this->assertSame([], $titles(['title' => 'road']));
        $this->assertSame(['Winter Orchard'], $titles(['title' => 'orch']));
    }

    #[Test]
    public function readers_without_the_view_permission_get_nothing(): void
    {
        $this->database()->table('anthology_series')->insert(['id' => 1, 'user_id' => 2, 'title' => 'Road', 'slug' => 'road', 'status' => 'ongoing', 'follower_count' => 0, 'last_chapter_at' => '2026-11-01 00:00:00', 'created_at' => '2026-11-01 00:00:00', 'updated_at' => '2026-11-01 00:00:00']);
        $this->database()->table('anthology_chapters')->insert(['series_id' => 1, 'discussion_id' => 1, 'position' => 1, 'created_at' => '2026-11-01 00:00:00']);
        // Before the first request: permissions load once per app.
        $this->database()->table('group_permission')->where('permission', 'anthology.view')->delete();

        $this->assertSame(403, $this->json('GET', '/api/anthology-series', 3)[0]);
        $this->assertContains($this->showSeries(1, 3)[0], [403, 404]);
        [, $body] = $this->json('GET', '/api/discussions/1', 3);
        $this->assertArrayNotHasKey('anthologySeries', $body['data']['attributes']);
    }

    #[Test]
    public function the_users_column_migration_runs_both_ways_whether_or_not_the_column_is_there(): void
    {
        $migration = require __DIR__.'/../../../migrations/2026_11_19_000000_drop_anthology_series_count_from_users.php';
        $schema = $this->database()->getSchemaBuilder();

        $migration['up']($schema);  // already gone: nothing to do
        $this->assertFalse($schema->hasColumn('users', 'anthology_series_count'));
        $migration['down']($schema);
        $migration['down']($schema);
        $this->assertTrue($schema->hasColumn('users', 'anthology_series_count'));
        $migration['up']($schema);
        $migration['up']($schema);
        $this->assertFalse($schema->hasColumn('users', 'anthology_series_count'));
    }

    #[Test]
    public function a_follower_reduced_to_a_guest_by_a_group_processor_hears_nothing_of_a_members_only_chapter(): void
    {
        $this->prepareDatabase([
            \Flarum\Tags\Tag::class => [['id' => 3, 'name' => 'Members', 'slug' => 'members', 'position' => 2, 'is_restricted' => 1]],
            'group_permission' => [['group_id' => \Flarum\Group\Group::MEMBER_ID, 'permission' => 'tag3.viewForum']],
            \Flarum\Discussion\Discussion::class => [['id' => 6, 'title' => 'Chapter 6', 'slug' => 'chapter-6', 'created_at' => \Carbon\Carbon::now(), 'last_posted_at' => \Carbon\Carbon::now(), 'user_id' => 2, 'first_post_id' => 6, 'comment_count' => 1, 'is_private' => 0]],
            \Flarum\Post\Post::class => [['id' => 6, 'discussion_id' => 6, 'created_at' => \Carbon\Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Members only</p></t>', 'number' => 1]],
            'discussion_tag' => [['discussion_id' => 6, 'tag_id' => 3]],
        ]);
        $series = $this->series();
        $this->add(1, $series['id']);
        $this->patchSeries($series['id'], 3, ['following' => true]);
        $this->patchSeries($series['id'], 4, ['following' => true]);

        // What flarum/suspend does to a suspended member: their permissions come from the guest group only.
        $processors = new \ReflectionProperty(User::class, 'groupProcessors');
        $before = $processors->getValue();
        User::addGroupProcessor(fn (User $user, array $ids) => $user->id === 3 ? [\Flarum\Group\Group::GUEST_ID] : $ids);
        try {
            $this->add(6, $series['id']);
        } finally {
            $processors->setValue(null, $before);
        }

        $sent = $this->database()->table('notifications')->where('type', 'anthologyNewChapter')->where('subject_id', 6)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([4], $sent);
    }
}
