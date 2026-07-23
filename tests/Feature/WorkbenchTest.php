<?php

declare(strict_types=1);

use EloquentQuerySpy\EloquentQuerySpy\QuerySpy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('demo_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('demo_posts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('title');
        $table->timestamps();
    });

    DB::table('demo_users')->insert(['name' => 'Alice', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('demo_users')->insert(['name' => 'Bob', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('demo_posts')->insert(['user_id' => 1, 'title' => 'Post 1', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('demo_posts')->insert(['user_id' => 1, 'title' => 'Post 2', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('demo_posts')->insert(['user_id' => 2, 'title' => 'Post 3', 'created_at' => now(), 'updated_at' => now()]);
});

afterEach(function () {
    Schema::dropIfExists('demo_posts');
    Schema::dropIfExists('demo_users');
});

it('detects N+1 queries with real database', function () {
    $spy = app(QuerySpy::class);
    $spy->getDetector()->setThreshold(2);
    $spy->boot();

    $users = DB::table('demo_users')->get();

    foreach ($users as $user) {
        DB::table('demo_posts')->where('user_id', $user->id)->get();
    }

    $issues = $spy->getNPlusOneIssues();

    expect($issues)->toHaveCount(1);
    expect($issues[0]->count)->toBe(2);
    expect($issues[0]->table)->toBe('demo_posts');
    expect($spy->getQueryCount())->toBe(3);
});

it('does not flag queries as N+1 with eager loading', function () {
    $spy = app(QuerySpy::class);
    $spy->boot();

    $users = DB::table('demo_users')->get();
    $userIds = $users->pluck('id');
    DB::table('demo_posts')->whereIn('user_id', $userIds)->get();

    $issues = $spy->getNPlusOneIssues();

    expect($issues)->toBeEmpty();
    expect($spy->getQueryCount())->toBe(2);
});

it('tracks query timing and ordering', function () {
    $spy = app(QuerySpy::class);
    $spy->boot();

    DB::table('demo_users')->get();
    DB::table('demo_posts')->get();

    $queries = $spy->getQueries();

    expect($queries)->toHaveCount(2);
    expect($queries[0]->sql)->toContain('demo_users');
    expect($queries[1]->sql)->toContain('demo_posts');
    expect($queries[0]->time)->toBeGreaterThan(0);
});

it('collects backtrace file and line info', function () {
    $spy = app(QuerySpy::class);
    $spy->boot();

    DB::table('demo_users')->get();

    $queries = $spy->getQueries();

    expect($queries)->toHaveCount(1);
    expect($queries[0]->file)->not->toBeNull();
});
