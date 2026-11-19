<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Which discussions are chapters of which series, in order. A discussion is a
 * chapter of one series at most (the primary key), and stops being one when
 * either side is deleted.
 */
return Migration::createTable('anthology_chapters', function (Blueprint $table) {
    $table->unsignedInteger('discussion_id')->primary();
    $table->unsignedInteger('series_id');
    $table->unsignedInteger('position')->default(0);
    $table->dateTime('created_at');

    $table->foreign('discussion_id')->references('id')->on('discussions')->cascadeOnDelete();
    $table->foreign('series_id')->references('id')->on('anthology_series')->cascadeOnDelete();
    $table->index(['series_id', 'position']);
});
