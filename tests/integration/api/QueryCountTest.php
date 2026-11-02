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
        $request = $this->request('GET', $path, ['authenticatedAs' => $actor])->withQueryParams($query);
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
        $this->json('POST', '/api/anthology/series/'.$first['id'].'/follow', 3);

        $directory = $this->queries('/api/anthology/series');
        $page = $this->queries('/api/anthology/series/'.$first['id']);
        $chapter = $this->queries('/api/discussions/1');

        $this->discussions(12);
        for ($i = 0; $i < 6; $i++) {
            $series = $this->series("Series $i", $i % 2 ? 2 : 1);
            $this->add(100 + $i, $series['id'], 1);
        }
        for ($i = 6; $i < 12; $i++) {
            $this->add(100 + $i, $first['id']);
            $this->database()->table('discussion_user')->insert(['user_id' => 3, 'discussion_id' => 100 + $i, 'last_read_post_number' => 1]);
        }

        $this->assertQueries($directory, '/api/anthology/series');
        $this->assertQueries($page, '/api/anthology/series/'.$first['id']);
        $this->assertQueries($chapter, '/api/discussions/1');
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
}
