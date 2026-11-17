<?php

namespace Ernestdefoe\Anthology;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A series of discussions (table anthology_series).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $cover_path
 * @property string $status
 * @property int $follower_count
 * @property Carbon $last_chapter_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User|null $user
 */
class Series extends AbstractModel
{
    public const STATUSES = ['ongoing', 'complete'];

    protected $table = 'anthology_series';

    public $timestamps = true;

    protected $casts = [
        'user_id' => 'integer',
        'follower_count' => 'integer',
        'last_chapter_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The chapters in reading order. Callers that show them to someone add
     * `whereVisibleTo($actor)`, so a chapter in a hidden tag is never listed.
     *
     * @return BelongsToMany<Discussion, $this>
     */
    public function discussions(): BelongsToMany
    {
        return $this->belongsToMany(Discussion::class, 'anthology_chapters', 'series_id', 'discussion_id')
            ->withPivot('position')
            ->orderBy('anthology_chapters.position')
            ->orderBy('anthology_chapters.discussion_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'anthology_follows', 'series_id', 'user_id');
    }
}
