<?php

use Illuminate\Database\Schema\Builder;

/**
 * The search words of the series that already exist, 500 at a time. The split
 * is SeriesWords::split's, written out so this migration never changes with it.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        $db->table('anthology_series')->select(['id', 'title'])->orderBy('id')->chunk(500, function ($rows) use ($db) {
            $words = [];
            foreach ($rows as $row) {
                $split = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $row->title), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach (array_slice(array_values(array_unique(array_map(fn ($w) => mb_substr($w, 0, 100), $split))), 0, 40) as $word) {
                    $words[] = ['series_id' => $row->id, 'word' => $word];
                }
            }
            $db->table('anthology_series_words')->insertOrIgnore($words);
        });
    },
    'down' => function (Builder $schema) {
        $schema->getConnection()->table('anthology_series_words')->delete();
    },
];
