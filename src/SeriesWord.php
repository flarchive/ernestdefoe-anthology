<?php

namespace Ernestdefoe\Anthology;

use Flarum\Database\AbstractModel;

/**
 * One word of a series title (table anthology_series_words), for search.
 *
 * @property int $series_id
 * @property string $word
 */
class SeriesWord extends AbstractModel
{
    protected $table = 'anthology_series_words';

    public $timestamps = false;

    public $incrementing = false;

    protected $casts = ['series_id' => 'integer'];
}
