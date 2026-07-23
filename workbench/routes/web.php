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
            echo $post->title;
        }
    }

    $report = $spy->analyse();

    return '<pre>'.json_encode($report, JSON_PRETTY_PRINT).'</pre>';
});

Route::get('/spy-eager', function () {
    $spy = app(QuerySpy::class);
    $spy->boot();

    $users = User::with('posts')->get();

    foreach ($users as $user) {
        foreach ($user->posts as $post) {
            echo $post->title;
        }
    }

    $report = $spy->analyse();

    return '<pre>'.json_encode($report, JSON_PRETTY_PRINT).'</pre>';
});
