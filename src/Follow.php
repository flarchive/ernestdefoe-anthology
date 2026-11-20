<?php

namespace Ernestdefoe\Anthology;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

/**
 * A member following a series (table anthology_follows), told about each new chapter.
 *
 * @property int $series_id
 * @property int $user_id
 * @property Carbon $created_at
 */
class Follow extends AbstractModel
{
    protected $table = 'anthology_follows';

    public $timestamps = false;

    public $incrementing = false;

    protected $casts = [
        'series_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];
}
