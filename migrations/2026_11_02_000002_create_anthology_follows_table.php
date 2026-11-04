<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/** Members following a series, notified of each new chapter. */
return Migration::createTable('anthology_follows', function (Blueprint $table) {
    $table->unsignedInteger('series_id');
    $table->unsignedInteger('user_id');
    $table->dateTime('created_at');

    $table->primary(['series_id', 'user_id']);
    $table->foreign('series_id')->references('id')->on('anthology_series')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->index('user_id');
});
