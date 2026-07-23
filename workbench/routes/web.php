<?php

use EloquentQuerySpy\EloquentQuerySpy\QuerySpy;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/spy', function () {
    $spy = app(QuerySpy::class);
    $spy->boot();

    $users = User::all();

    foreach ($users as $user) {
        foreach ($user->posts as $post) {
            foreach ($post->comments as $comment) {
                echo "<p>User: {$user->name} | Post: {$post->title} | Comment: {$comment->body}</p>";
            }
        }
    }

    $report = $spy->analyse();

    return response(json_encode($report, JSON_PRETTY_PRINT))
        ->header('Content-Type', 'application/json');
});

Route::get('/spy-eager', function () {
    $spy = app(QuerySpy::class);
    $spy->boot();

    $users = User::with('posts.comments')->get();

    foreach ($users as $user) {
        foreach ($user->posts as $post) {
            foreach ($post->comments as $comment) {
                echo "<p>User: {$user->name} | Post: {$post->title} | Comment: {$comment->body}</p>";
            }
        }
    }

    $report = $spy->analyse();

    return response(json_encode($report, JSON_PRETTY_PRINT))
        ->header('Content-Type', 'application/json');
});
