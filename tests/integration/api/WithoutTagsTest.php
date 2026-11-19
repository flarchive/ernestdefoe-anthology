<?php

namespace Ernestdefoe\Anthology\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/** Tags off: nothing of Tags is loaded, the directory has no tag menu and a tag filter narrows nothing. */
class WithoutTagsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('ernestdefoe-anthology');
        $this->prepareDatabase(['users' => [$this->normalUser()]]);
    }

    #[Test]
    public function the_directory_works_without_tags(): void
    {
        $create = $this->send($this->request('POST', '/api/anthology-series', ['authenticatedAs' => 2, 'json' => ['data' => ['type' => 'anthology-series', 'attributes' => ['title' => 'Road']]]]));
        $this->assertSame(201, $create->getStatusCode(), (string) $create->getBody());

        $response = $this->send($this->request('GET', '/api/anthology-series', ['authenticatedAs' => 2])
            ->withQueryParams(['filter' => ['mine' => '1', 'tag' => 'anything'], 'withTags' => '1']));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(['Road'], array_column(array_column($body['data'], 'attributes'), 'title'));
        $this->assertNull($body['meta']['tags'] ?? null);
    }
}
