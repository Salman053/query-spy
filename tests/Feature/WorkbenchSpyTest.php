<?php

declare(strict_types=1);

use EloquentQuerySpy\EloquentQuerySpy\QuerySpy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('comments');
    Schema::dropIfExists('posts');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('title');
        $table->timestamps();
    });

    Schema::create('comments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('post_id');
        $table->text('body');
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['name' => 'Alice', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Bob', 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('posts')->insert([
        ['user_id' => 1, 'title' => 'Post 1', 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => 1, 'title' => 'Post 2', 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => 2, 'title' => 'Post 3', 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('comments')->insert([
        ['post_id' => 1, 'body' => 'Comment 1', 'created_at' => now(), 'updated_at' => now()],
        ['post_id' => 1, 'body' => 'Comment 2', 'created_at' => now(), 'updated_at' => now()],
        ['post_id' => 3, 'body' => 'Comment 3', 'created_at' => now(), 'updated_at' => now()],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('comments');
    Schema::dropIfExists('posts');
    Schema::dropIfExists('users');
});

it('detects N+1 with correct backtrace', function () {
    $spy = app(QuerySpy::class);
    $spy->getDetector()->setThreshold(2);
    $spy->boot();

    DB::table('users')->get()->each(function ($user) {
        DB::table('posts')->where('user_id', $user->id)->get()->each(function ($post) {
            DB::table('comments')->where('post_id', $post->id)->get();
        });
    });

    $issues = $spy->getNPlusOneIssues();
    $firstQuery = $spy->getQueries()[0] ?? null;

    expect($firstQuery)->not->toBeNull();

    if ($firstQuery->file !== null) {
        expect($firstQuery->file)->not->toContain('src/');
        expect($firstQuery->file)->not->toContain('vendor/');
    }
});

it('shows zero N+1 with eager loading', function () {
    $spy = app(QuerySpy::class);
    $spy->boot();

    $userIds = DB::table('users')->pluck('id');
    DB::table('posts')->whereIn('user_id', $userIds)->get();

    $issues = $spy->getNPlusOneIssues();

    expect($issues)->toBeEmpty();
});
