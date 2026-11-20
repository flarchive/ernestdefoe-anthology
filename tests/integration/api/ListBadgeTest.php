<?php

namespace Ernestdefoe\Anthology\Tests\integration\api;

use Ernestdefoe\Anthology\Tests\integration\AnthologyTestCase;
use PHPUnit\Framework\Attributes\Test;

/** The series badge beside a chapter's tags in the discussion list. */
class ListBadgeTest extends AnthologyTestCase
{
    #[Test]
    public function chapters_in_the_list_carry_their_series_and_others_carry_none(): void
    {
        $series = $this->series();
        $this->add(1, $series['id']);
        $secret = $this->series('Secret Saga', 1);
        $this->add(5, $secret['id'], 1);

        foreach ([3, null] as $actor) {
            $badges = $this->badges($actor);
            $this->assertSame(['id' => $series['id'], 'slug' => 'the-long-road', 'title' => 'The Long Road'], $badges[1]);
            $this->assertNull($badges[2]);
            $this->assertArrayNotHasKey(5, $badges); // the hidden chapter isn't listed, so neither is its series
        }
        $this->assertSame('Secret Saga', $this->badges(1)[5]['title']);

        // Only lists: a discussion's own page has anthologySeries instead.
        [, $page] = $this->json('GET', '/api/discussions/1', 3);
        $this->assertArrayNotHasKey('anthologySeriesBadge', $page['data']['attributes']);
    }

    #[Test]
    public function readers_who_cannot_view_series_get_no_badge(): void
    {
        $this->database()->table('anthology_series')->insert(['id' => 1, 'user_id' => 2, 'title' => 'Road', 'slug' => 'road', 'status' => 'ongoing', 'follower_count' => 0, 'last_chapter_at' => '2026-11-01 00:00:00', 'created_at' => '2026-11-01 00:00:00', 'updated_at' => '2026-11-01 00:00:00']);
        $this->database()->table('anthology_chapters')->insert(['series_id' => 1, 'discussion_id' => 1, 'position' => 1, 'created_at' => '2026-11-01 00:00:00']);
        // Before the first request: permissions load once per app.
        $this->database()->table('group_permission')->where('permission', 'anthology.view')->delete();

        $this->assertSame('absent', $this->badges(3)[1]);
        $this->assertSame('absent', $this->badges(null)[1]);
    }
}
