<?php

namespace Ernestdefoe\Anthology;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

/**
 * A discussion's place in a series (table anthology_chapters).
 *
 * @property int $discussion_id
 * @property int $series_id
 * @property int $position
 * @property Carbon $created_at
 */
class Chapter extends AbstractModel
{
    protected $table = 'anthology_chapters';

    protected $primaryKey = 'discussion_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'discussion_id' => 'integer',
        'series_id' => 'integer',
        'position' => 'integer',
        'created_at' => 'datetime',
    ];
}
