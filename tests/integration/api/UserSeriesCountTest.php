<?php

namespace Ernestdefoe\Anthology\Tests\integration\api;

use Ernestdefoe\Anthology\Tests\integration\AnthologyTestCase;
use PHPUnit\Framework\Attributes\Test;

/** The count beside the profile's Series tab: only series this reader can open. */
class UserSeriesCountTest extends AnthologyTestCase
{
    private function seriesCount(?int $actor): ?int
    {
        [$status, $body, $raw] = $this->json('GET', '/api/users/2', $actor);
        $this->assertSame(200, $status, $raw);

        return $body['data']['attributes']['anthologySeriesCount'] ?? null;
    }

    #[Test]
    public function the_count_holds_only_series_the_reader_can_open(): void
    {
        // Two published series, an empty one (a draft) and one whose only chapter is in the hidden tag.
        $this->add(1, $this->series('First')['id']);
        $this->add(2, $this->series('Second')['id']);
        $this->series('Draft');
        $secret = $this->series('Secret');
        $this->add(5, $secret['id'], 1);

        $this->assertSame(2, $this->seriesCount(2), 'the owner');
        $this->assertSame(2, $this->seriesCount(3), 'another member');
        $this->assertSame(2, $this->seriesCount(4), 'a moderator: still no draft');
        $this->assertSame(2, $this->seriesCount(null), 'a guest');
        $this->assertSame(3, $this->seriesCount(1), 'the admin, who can read the hidden tag');
    }

    #[Test]
    public function the_count_is_never_on_user_lists(): void
    {
        $this->add(1, $this->series()['id']);

        [$status, $body, $raw] = $this->json('GET', '/api/users', 1);
        $this->assertSame(200, $status, $raw);
        foreach ($body['data'] as $user) {
            $this->assertArrayNotHasKey('anthologySeriesCount', $user['attributes']);
        }
    }

    #[Test]
    public function readers_without_the_permission_get_no_count(): void
    {
        $this->database()->table('anthology_series')->insert(['id' => 1, 'user_id' => 2, 'title' => 'Road', 'slug' => 'road', 'status' => 'ongoing', 'follower_count' => 0, 'last_chapter_at' => '2026-11-01 00:00:00', 'created_at' => '2026-11-01 00:00:00', 'updated_at' => '2026-11-01 00:00:00']);
        $this->database()->table('anthology_chapters')->insert(['series_id' => 1, 'discussion_id' => 1, 'position' => 1, 'created_at' => '2026-11-01 00:00:00']);
        // Before the first request: permissions load once per app.
        $this->database()->table('group_permission')->where('permission', 'anthology.view')->delete();

        $this->assertNull($this->seriesCount(3));
    }
}
