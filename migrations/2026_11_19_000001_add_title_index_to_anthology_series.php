<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

// The directory's A–Z sort orders by title.
return [
    'up' => function (Builder $schema) {
        $schema->table('anthology_series', function (Blueprint $table) {
            $table->index('title');
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('anthology_series', function (Blueprint $table) {
            $table->dropIndex(['title']);
        });
    },
];
