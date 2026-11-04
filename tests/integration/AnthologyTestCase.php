<?php

namespace Ernestdefoe\Anthology\Tests\integration;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;

/**
 * Users: 1 admin, 2 normal (a writer), 3 carol (a reader), 4 mo (moderator).
 * Discussions 1–3 are normal's, 4 is carol's, 5 is normal's in the "Secret"
 * tag that only the admin can see.
 */
abstract class AnthologyTestCase extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'ernestdefoe-anthology');

        $discussions = [];
        $posts = [];
        foreach ([1 => 2, 2 => 2, 3 => 2, 4 => 3, 5 => 2] as $id => $user) {
            $discussions[] = ['id' => $id, 'title' => "Chapter $id", 'slug' => "chapter-$id", 'created_at' => Carbon::now()->subDays(10 - $id), 'last_posted_at' => Carbon::now(), 'user_id' => $user, 'first_post_id' => $id, 'comment_count' => 1, 'is_private' => 0];
            $posts[] = ['id' => $id, 'discussion_id' => $id, 'created_at' => Carbon::now()->subDays(10 - $id), 'user_id' => $user, 'type' => 'comment', 'content' => '<t><p>Once upon a time</p></t>', 'number' => 1];
        }

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'carol', 'email' => 'carol@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'mo', 'email' => 'mo@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_user' => [
                ['user_id' => 4, 'group_id' => Group::MODERATOR_ID],
            ],
            Tag::class => [
                ['id' => 1, 'name' => 'Stories', 'slug' => 'stories', 'position' => 0],
                ['id' => 2, 'name' => 'Secret', 'slug' => 'secret', 'position' => 1, 'is_restricted' => 1],
            ],
            Discussion::class => $discussions,
            Post::class => $posts,
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 2, 'tag_id' => 1],
                ['discussion_id' => 3, 'tag_id' => 1],
                ['discussion_id' => 4, 'tag_id' => 1],
                ['discussion_id' => 5, 'tag_id' => 2],
            ],
        ]);
    }

    /** @return array{0: int, 1: array<string, mixed>|null, 2: string} */
    protected function json(string $method, string $path, ?int $actor = null, ?array $body = null, array $query = []): array
    {
        $options = $actor ? ['authenticatedAs' => $actor] : [];
        if ($body !== null) {
            $options['json'] = $body;
        }

        $request = $this->request($method, $path, $options);
        if ($query) {
            $request = $request->withQueryParams($query);
        }
        $response = $this->send($actor ? $request : $request->withAttribute('bypassCsrfToken', true));
        $raw = (string) $response->getBody();

        return [$response->getStatusCode(), json_decode($raw, true), $raw];
    }

    /** @return array<string, mixed> */
    protected function series(string $title = 'The Long Road', int $actor = 2): array
    {
        [$status, $body, $raw] = $this->json('POST', '/api/anthology/series', $actor, ['data' => ['title' => $title, 'description' => 'A long story.']]);
        $this->assertSame(201, $status, $raw);

        return $body['data'];
    }

    /** @return array<string, mixed>|null */
    protected function add(int $discussionId, int $seriesId, int $actor = 2, int $expect = 200): ?array
    {
        [$status, $body, $raw] = $this->json('POST', '/api/anthology/chapters', $actor, ['data' => ['discussionId' => $discussionId, 'seriesId' => $seriesId]]);
        $this->assertSame($expect, $status, $raw);

        return $body['data'] ?? null;
    }
}
