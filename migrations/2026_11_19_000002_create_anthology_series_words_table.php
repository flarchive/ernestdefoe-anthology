<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * The words of each series title, lowercased, so the directory's search finds
 * a title by the start of any of its words ("glass" finds "The Glass Orchard")
 * without a leading wildcard; MySQL serves the prefix match from the word index.
 * SeriesWords keeps it in step whenever a title is saved; the next migration
 * fills it for the series that exist.
 */
return Migration::createTable('anthology_series_words', function (Blueprint $table) {
    $table->unsignedInteger('series_id');
    $table->string('word', 100);

    $table->primary(['series_id', 'word']);
    $table->index('word');
    $table->foreign('series_id')->references('id')->on('anthology_series')->cascadeOnDelete();
});
