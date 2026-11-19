<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

// The profile's series count is now worked out per reader (only series they can
// open), so the stored total leaves the core users table. Safe to run whether or
// not the column is there, both ways.
return [
    'up' => function (Builder $schema) {
        if ($schema->hasColumn('users', 'anthology_series_count')) {
            $schema->table('users', function (Blueprint $table) {
                $table->dropColumn('anthology_series_count');
            });
        }
    },
    'down' => function (Builder $schema) {
        if (! $schema->hasColumn('users', 'anthology_series_count')) {
            $schema->table('users', function (Blueprint $table) {
                $table->unsignedInteger('anthology_series_count')->default(0);
            });
        }
    },
];
