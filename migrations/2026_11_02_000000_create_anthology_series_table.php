<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * A series: a title and cover that discussions are added to as chapters.
 * `last_chapter_at` (set to now on creation) orders the directory by "latest
 * chapter"; `follower_count` orders it by popularity. Both are kept here so the
 * directory never counts anything to sort.
 */
return Migration::createTable('anthology_series', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('user_id')->nullable();
    $table->string('title', 200);
    $table->string('slug', 200);
    $table->text('description')->nullable();
    $table->string('cover_path', 100)->nullable();
    // ongoing | complete
    $table->string('status', 16)->default('ongoing');
    $table->unsignedInteger('follower_count')->default(0);
    $table->dateTime('last_chapter_at');
    $table->timestamps();

    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
    $table->index('user_id');
    $table->index('last_chapter_at');
    $table->index('follower_count');
});
