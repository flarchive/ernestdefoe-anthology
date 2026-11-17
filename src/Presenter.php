<?php

namespace Ernestdefoe\Anthology;

use Flarum\User\User;
use Illuminate\Support\Str;

/** The plain arrays Anthology's API sends. Nothing here queries: callers load what it reads. */
class Presenter
{
    public function __construct(private Covers $covers)
    {
    }

    /** @return array<string, mixed> */
    public function card(Series $series, User $actor): array
    {
        return [
            'id' => $series->id,
            'slug' => $series->slug,
            'title' => $series->title,
            'excerpt' => $series->description ? Str::limit(trim((string) preg_replace('/\s+/', ' ', $series->description)), 160) : null,
            'coverUrl' => $this->covers->url($series),
            'status' => $series->status,
            'chapterCount' => (int) ($series->getAttribute('chapter_count') ?? 0),
            'followerCount' => $series->follower_count,
            'lastChapterAt' => $series->last_chapter_at->toIso8601String(),
            'author' => $this->person($series->user),
            'canEdit' => Access::canEdit($actor, $series),
        ];
    }

    /** @return array<string, mixed>|null */
    public function person(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'username' => $user->username,
            'displayName' => $user->display_name,
            'avatarUrl' => $user->avatar_url,
            'slug' => $user->slug ?? $user->username,
        ] : null;
    }
}
