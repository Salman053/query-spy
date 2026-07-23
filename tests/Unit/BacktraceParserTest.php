<?php

declare(strict_types=1);

use EloquentQuerySpy\EloquentQuerySpy\Collectors\BacktraceParser;

it('parses a simple backtrace frame', function () {
    $parser = new BacktraceParser;

    $backtrace = [
        [
            'file' => '/project/vendor/illuminate/database/Connection.php',
            'line' => 100,
            'function' => 'runQueryCallback',
            'class' => 'Illuminate\\Database\\Connection',
            'type' => '->',
        ],
        [
            'file' => '/project/app/Models/User.php',
            'line' => 42,
            'function' => 'all',
            'class' => 'App\\Models\\User',
            'type' => '::',
        ],
    ];

    [$file, $line, $caller] = $parser->parse($backtrace, '/project');

    expect($file)->toBe('app/Models/User.php');
    expect($line)->toBe(42);
    expect($caller)->toBe('User::all');
});

it('skips internal vendor frames', function () {
    $parser = new BacktraceParser;

    $backtrace = [
        [
            'file' => '/project/vendor/laravel/framework/src/Illuminate/Database/Connection.php',
            'line' => 500,
            'function' => 'run',
            'class' => 'Illuminate\\Database\\Connection',
        ],
        [
            'file' => '/project/vendor/laravel/framework/src/Illuminate/Database/Connection.php',
            'line' => 450,
            'function' => 'select',
            'class' => 'Illuminate\\Database\\Connection',
        ],
        [
            'file' => '/project/app/Http/Controllers/PostController.php',
            'line' => 25,
            'function' => 'index',
            'class' => 'App\\Http\\Controllers\\PostController',
            'type' => '->',
        ],
    ];

    [$file, $line, $caller] = $parser->parse($backtrace, '/project');

    expect($file)->toBe('app/Http/Controllers/PostController.php');
    expect($line)->toBe(25);
    expect($caller)->toBe('PostController->index');
});

it('returns null values for empty backtrace', function () {
    $parser = new BacktraceParser;

    [$file, $line, $caller] = $parser->parse([], '/project');

    expect($file)->toBeNull();
    expect($line)->toBeNull();
    expect($caller)->toBeNull();
});

it('handles frames without file info', function () {
    $parser = new BacktraceParser;

    $backtrace = [
        [
            'function' => 'someFunction',
            'class' => 'SomeClass',
            'type' => '::',
        ],
    ];

    [$file, $line, $caller] = $parser->parse($backtrace, '/project');

    expect($file)->toBeNull();
    expect($line)->toBeNull();
    expect($caller)->toBe('SomeClass::someFunction');
});

it('skips frames outside the project path', function () {
    $parser = new BacktraceParser;

    $backtrace = [
        [
            'file' => '/usr/share/php/SomeInternal.php',
            'line' => 50,
            'function' => 'internalFunc',
        ],
        [
            'file' => '/project/app/Services/UserService.php',
            'line' => 10,
            'function' => 'getUsers',
            'class' => 'App\\Services\\UserService',
            'type' => '->',
        ],
    ];

    [$file, $line, $caller] = $parser->parse($backtrace, '/project');

    expect($file)->toBe('app/Services/UserService.php');
    expect($line)->toBe(10);
});
