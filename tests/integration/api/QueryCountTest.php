<?php

namespace Ernestdefoe\Anthology\Tests\integration\api;

use Carbon\Carbon;
use Ernestdefoe\Anthology\Tests\integration\AnthologyTestCase;
use PHPUnit\Framework\Attributes\Test;

/** Every page costs the same number of queries for one series or chapter as for many. */
class QueryCountTest extends AnthologyTestCase
{
    /** @var list<string> */
    private array $log = [];

    private bool $listening = false;

    private function queries(string $path, array $query = [], int $actor = 3): int
    {
        $this->log = [];
        $this->listening || $this->database()->listen(function ($event) {
            // Core refreshes "last seen" and collects expired tokens on
            // whichever request comes due: not this page's cost.
            if (! preg_match('/^update .*users.* set .*last_seen_at|^delete from .*(access_tokens|email_tokens|password_tokens|registration_tokens)/i', $event->sql)) {
                $this->log[] = $event->sql;
            }
        });
        $this->listening = true;
        // Actor 0: a guest.
        $request = $this->request('GET', $path, $actor ? ['authenticatedAs' => $actor] : [])->withQueryParams($query);
        $this->send($request); // warm
        $this->log = [];
        $response = $this->send($request);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return count($this->log);
    }

    private function assertQueries(int $expected, string $path, array $query = [], int $actor = 3): void
    {
        $this->assertSame($expected, $this->queries($path, $query, $actor), $path."\n".implode("\n", $this->log));
    }

    /** More discussions by normal, ids from 100. */
    private function discussions(int $count): void
    {
        $now = Carbon::now();
        for ($i = 0; $i < $count; $i++) {
            $id = 100 + $i;
            $this->database()->table('discussions')->insert(['id' => $id, 'title' => "Part $id", 'slug' => "part-$id", 'created_at' => $now, 'last_posted_at' => $now, 'user_id' => 2, 'comment_count' => 1, 'is_private' => 0]);
            $this->database()->table('posts')->insert(['id' => $id, 'discussion_id' => $id, 'created_at' => $now, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Text</p></t>', 'number' => 1]);
            $this->database()->table('discussions')->where('id', $id)->update(['first_post_id' => $id]);
            $this->database()->table('discussion_tag')->insert(['discussion_id' => $id, 'tag_id' => 1]);
        }
    }

    #[Test]
    public function the_directory_and_a_series_page_do_not_grow(): void
    {
        $first = $this->series();
        $this->add(1, $first['id']);
        $this->database()->table('discussion_user')->insert(['user_id' => 3, 'discussion_id' => 1, 'last_read_post_number' => 1]);
        $this->patchSeries($first['id'], 3, ['following' => true]);

        $published = ['filter' => ['published' => '1']];
        $tagsMenu = ['filter' => ['published' => '1', 'tag' => 'stories'], 'withTags' => '1'];
        // A member (with read state and a follow) and a guest, on one series of one chapter.
        $cost = [];
        foreach ([3, 0] as $actor) {
            $cost[$actor] = [
                'directory' => $this->queries('/api/anthology-series', $published, $actor),
                'tagged' => $this->queries('/api/anthology-series', $tagsMenu, $actor),
                'page' => $this->queries('/api/anthology-series/'.$first['id'], [], $actor),
                'chapter' => $this->queries('/api/discussions/1', [], $actor),
            ];
        }

        // Then 20 series in the directory, and 7 chapters (6 of them read) in the first.
        $this->discussions(25);
        for ($i = 0; $i < 19; $i++) {
            $series = $this->series("Series $i", $i % 2 ? 2 : 4);
            $this->add(100 + $i, $series['id'], 4);
        }
        for ($i = 19; $i < 25; $i++) {
            $this->add(100 + $i, $first['id']);
            $this->database()->table('discussion_user')->insert(['user_id' => 3, 'discussion_id' => 100 + $i, 'last_read_post_number' => 1]);
        }
        $this->assertCount(20, $this->listSeries(3, $published)[0]);

        foreach ([3, 0] as $actor) {
            $this->assertQueries($cost[$actor]['directory'], '/api/anthology-series', $published, $actor);
            $this->assertQueries($cost[$actor]['tagged'], '/api/anthology-series', $tagsMenu, $actor);
            $this->assertSame($cost[$actor]['directory'] + 1, $cost[$actor]['tagged'], 'the tag menu is one query');
            $this->assertQueries($cost[$actor]['page'], '/api/anthology-series/'.$first['id'], [], $actor);
            $this->assertQueries($cost[$actor]['chapter'], '/api/discussions/1', [], $actor);
        }
    }

    #[Test]
    public function discussion_lists_cost_nothing_extra(): void
    {
        $this->discussions(12);
        $before = $this->queries('/api/discussions');

        $series = $this->series();
        foreach (range(100, 111) as $id) {
            $this->add($id, $series['id']);
        }

        $this->assertQueries($before, '/api/discussions');
    }

    #[Test]
    public function the_list_badge_is_one_query_for_the_page(): void
    {
        $series = $this->series();
        $this->add(1, $series['id']);
        $badges = fn (int $actor) => array_column(array_column($this->json('GET', '/api/discussions', $actor)[1]['data'], 'attributes'), 'anthologySeriesBadge');
        foreach ([3, 0] as $actor) {
            $one[$actor] = $this->queries('/api/discussions', [], $actor);
        }

        // A full page (20 discussions): nearly every one a chapter, each in its own series.
        $this->discussions(19);
        for ($i = 0; $i < 19; $i++) {
            $this->add(100 + $i, $this->series("Series $i", 4)['id'], 4);
        }
        $this->assertGreaterThanOrEqual(16, count(array_filter($badges(3))));

        foreach ([3, 0] as $actor) {
            $this->assertQueries($one[$actor], '/api/discussions', [], $actor);
        }
    }
}
