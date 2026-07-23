<?php

declare(strict_types=1);

use EloquentQuerySpy\EloquentQuerySpy\Collectors\NPlusOneDetector;
use EloquentQuerySpy\EloquentQuerySpy\Collectors\QueryHasher;
use EloquentQuerySpy\EloquentQuerySpy\Data\QueryExecution;

beforeEach(function () {
    $this->detector = new NPlusOneDetector(new QueryHasher, threshold: 3);
});

it('returns empty array when no queries', function () {
    expect($this->detector->detect([]))->toBe([]);
});

it('returns empty array when below threshold', function () {
    $queries = [
        new QueryExecution('SELECT * FROM users WHERE id = ?', [1], 1.0, 'mysql', hash: md5('SELECT * FROM users WHERE id = ?')),
        new QueryExecution('SELECT * FROM users WHERE id = ?', [2], 1.0, 'mysql', hash: md5('SELECT * FROM users WHERE id = ?')),
    ];

    expect($this->detector->detect($queries))->toBe([]);
});

it('detects N+1 when threshold exceeded', function () {
    $sql = 'SELECT * FROM posts WHERE user_id = ?';
    $hash = md5((new QueryHasher)->normalize($sql));

    $queries = [];
    $queries[] = new QueryExecution('SELECT * FROM users', [], 1.0, 'mysql', hash: md5('SELECT * FROM users'));
    for ($i = 0; $i < 5; $i++) {
        $queries[] = new QueryExecution($sql, [$i], 1.0, 'mysql', hash: $hash);
    }

    $issues = $this->detector->detect($queries);

    expect($issues)->toHaveCount(1);
    expect($issues[0]->count)->toBe(5);
    expect($issues[0]->relation)->not->toBeNull();
});

it('detects N+1 and identifies the parent query', function () {
    $hasher = new QueryHasher;
    $sql = 'SELECT * FROM comments WHERE post_id = ?';
    $hash = $hasher->hash($sql);

    $queries = [];
    $queries[] = new QueryExecution('SELECT * FROM posts', [], 1.0, 'mysql', hash: $hasher->hash('SELECT * FROM posts'));
    for ($i = 0; $i < 5; $i++) {
        $queries[] = new QueryExecution($sql, [$i], 1.0, 'mysql', hash: $hash);
    }

    $issues = $this->detector->detect($queries);

    expect($issues)->toHaveCount(1);
    expect($issues[0]->parentQuery)->not->toBeNull();
    expect($issues[0]->parentQuery->sql)->toBe('SELECT * FROM posts');
});

it('respects custom threshold', function () {
    $detector = new NPlusOneDetector(new QueryHasher, threshold: 2);

    $hash = md5('SELECT * FROM logs WHERE user_id = ?');
    $queries = [];
    for ($i = 0; $i < 3; $i++) {
        $queries[] = new QueryExecution('SELECT * FROM logs WHERE user_id = ?', [$i], 1.0, 'mysql', hash: $hash);
    }

    expect($detector->detect($queries))->toHaveCount(1);

    $detector->setThreshold(5);
    expect($detector->detect($queries))->toHaveCount(0);
});

it('generates suggested with statement', function () {
    $hasher = new QueryHasher;
    $hash = $hasher->hash('SELECT * FROM posts WHERE user_id = ?');

    $queries = [];
    $queries[] = new QueryExecution('SELECT * FROM users', [], 1.0, 'mysql', hash: $hasher->hash('SELECT * FROM users'));
    for ($i = 0; $i < 5; $i++) {
        $queries[] = new QueryExecution('SELECT * FROM posts WHERE user_id = ?', [$i], 1.0, 'mysql', hash: $hash);
    }

    $issues = $this->detector->detect($queries);

    expect($issues)->toHaveCount(1);
    expect($issues[0]->suggestedWith)->toContain('->with(');
    expect($issues[0]->suggestedFix())->toContain('->with(');
});

it('filters non-select queries', function () {
    $hasher = new QueryHasher;
    $selectHash = $hasher->hash('SELECT * FROM logs WHERE user_id = ?');
    $insertHash = $hasher->hash('INSERT INTO logs (message) VALUES (?)');

    $queries = [];
    for ($i = 0; $i < 5; $i++) {
        $queries[] = new QueryExecution('SELECT * FROM logs WHERE user_id = ?', [$i], 1.0, 'mysql', hash: $selectHash);
    }
    for ($i = 0; $i < 5; $i++) {
        $queries[] = new QueryExecution('INSERT INTO logs (message) VALUES (?)', ['test'], 1.0, 'mysql', hash: $insertHash);
    }

    $issues = $this->detector->detect($queries);

    expect($issues)->toHaveCount(1);
});
