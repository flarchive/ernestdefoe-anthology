<?php

namespace Ernestdefoe\Anthology\Tests\integration\api;

use Ernestdefoe\Anthology\Tests\integration\AnthologyTestCase;
use PHPUnit\Framework\Attributes\Test;

/** The admin switch "Show series badge in discussion lists", off. */
class ListBadgeOffTest extends AnthologyTestCase
{
    protected function setUp(): void
    {
        // Settings are read once, when the app boots.
        $this->setting('ernestdefoe-anthology.list_badge', '0');
        parent::setUp();
    }

    #[Test]
    public function the_admin_switch_turns_it_off(): void
    {
        $this->add(1, $this->series()['id']);

        $this->assertSame('absent', $this->badges(3)[1]);
    }
}
