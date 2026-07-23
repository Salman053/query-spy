<?php

declare(strict_types=1);

use EloquentQuerySpy\EloquentQuerySpy\Collectors\BacktraceParser;
use EloquentQuerySpy\EloquentQuerySpy\Collectors\QueryCollector;
use EloquentQuerySpy\EloquentQuerySpy\Collectors\QueryHasher;

beforeEach(function () {
    $this->collector = new QueryCollector(
        new QueryHasher,
        new BacktraceParser,
    );
});

it('collects a query execution', function () {
    $query = $this->collector->collect(
        sql: 'SELECT * FROM users WHERE id = ?',
        bindings: [1],
        time: 5.2,
        connection: 'mysql',
    );

    expect($query)->not->toBeNull();
    expect($query->sql)->toBe('SELECT * FROM users WHERE id = ?');
    expect($query->bindings)->toBe([1]);
    expect($query->time)->toBe(5.2);
    expect($query->connection)->toBe('mysql');
    expect($query->hash)->not->toBeNull();
});

it('counts collected queries', function () {
    $this->collector->collect('SELECT * FROM users', [], 1.0);
    $this->collector->collect('SELECT * FROM posts', [], 2.0);

    expect($this->collector->count())->toBe(2);
});

it('calculates total time', function () {
    $this->collector->collect('SELECT 1', [], 10.0);
    $this->collector->collect('SELECT 2', [], 20.0);

    expect($this->collector->totalTime())->toBe(30.0);
});

it('clears all collected queries', function () {
    $this->collector->collect('SELECT * FROM users', [], 1.0);
    expect($this->collector->count())->toBe(1);

    $this->collector->clear();
    expect($this->collector->count())->toBe(0);
});

it('detects duplicate queries by hash', function () {
    $this->collector->collect('SELECT * FROM users WHERE id = ?', [1], 1.0);
    $this->collector->collect('SELECT * FROM users WHERE id = ?', [2], 1.0);
    $this->collector->collect('SELECT * FROM posts', [], 1.0);

    $duplicates = $this->collector->getDuplicateQueries();

    expect($duplicates)->toHaveCount(1);
    expect($duplicates[0])->toHaveCount(2);
});

it('detects slow queries', function () {
    $this->collector->collect('SELECT 1', [], 10.0);
    $this->collector->collect('SELECT 2', [], 200.0);
    $this->collector->collect('SELECT 3', [], 50.0);

    $slow = $this->collector->getSlowQueries(100);

    expect($slow)->toHaveCount(1);
    expect($slow[0]->time)->toBe(200.0);
});

it('excludes queries matching patterns', function () {
    $this->collector->setExcludedPatterns(['telescope']);

    $this->collector->collect('SELECT * FROM telescope_entries', [], 1.0);
    $this->collector->collect('SELECT * FROM users', [], 1.0);

    expect($this->collector->count())->toBe(1);
});

it('tracks query occurrences', function () {
    $this->collector->collect('SELECT * FROM users WHERE id = ?', [1], 1.0);
    $this->collector->collect('SELECT * FROM users WHERE id = ?', [2], 1.0);

    $queries = $this->collector->getQueries();

    expect($queries[0]->occurrence)->toBe(1);
    expect($queries[1]->occurrence)->toBe(2);
});

it('converts to array', function () {
    $this->collector->collect('SELECT * FROM users', [], 1.0);

    $data = $this->collector->toArray();

    expect($data)->toHaveKey('total_queries');
    expect($data)->toHaveKey('total_time');
    expect($data)->toHaveKey('queries');
    expect($data)->toHaveKey('duplicates');
    expect($data['total_queries'])->toBe(1);
});

it('getQueriesByHash returns matching queries', function () {
    $hash = (new QueryHasher)->hash('SELECT * FROM users WHERE id = ?');

    $this->collector->collect('SELECT * FROM users WHERE id = ?', [1], 1.0);
    $this->collector->collect('SELECT * FROM posts', [], 1.0);

    $matches = $this->collector->getQueryByHash($hash);

    expect($matches)->toHaveCount(1);
    expect($matches[0]->sql)->toBe('SELECT * FROM users WHERE id = ?');
});

it('returns empty array for unknown hash', function () {
    expect($this->collector->getQueryByHash('nonexistent'))->toBe([]);
});
