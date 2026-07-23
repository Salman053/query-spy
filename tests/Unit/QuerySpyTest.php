<?php

declare(strict_types=1);

use EloquentQuerySpy\EloquentQuerySpy\Collectors\BacktraceParser;
use EloquentQuerySpy\EloquentQuerySpy\Collectors\NPlusOneDetector;
use EloquentQuerySpy\EloquentQuerySpy\Collectors\QueryCollector;
use EloquentQuerySpy\EloquentQuerySpy\Collectors\QueryHasher;
use EloquentQuerySpy\EloquentQuerySpy\QuerySpy;

beforeEach(function () {
    $this->spy = new QuerySpy(
        new QueryCollector(new QueryHasher, new BacktraceParser),
        new NPlusOneDetector(new QueryHasher),
    );
});

it('starts and stops listening', function () {
    expect($this->spy->isListening())->toBeFalse();

    $this->spy->startListening();
    expect($this->spy->isListening())->toBeTrue();

    $this->spy->stopListening();
    expect($this->spy->isListening())->toBeFalse();
});

it('boots only once', function () {
    expect($this->spy->isBooted())->toBeFalse();

    $this->spy->boot();
    expect($this->spy->isBooted())->toBeTrue();

    $this->spy->boot();
    expect($this->spy->isBooted())->toBeTrue();
});

it('returns zero queries when none collected', function () {
    expect($this->spy->getQueryCount())->toBe(0);
    expect($this->spy->getTotalTime())->toBe(0.0);
    expect($this->spy->getQueries())->toBe([]);
});

it('collects queries via the collector', function () {
    $this->spy->getCollector()->collect('SELECT * FROM users', [], 5.0, 'mysql');

    expect($this->spy->getQueryCount())->toBe(1);
    expect($this->spy->getTotalTime())->toBe(5.0);
    expect($this->spy->getQueries())->toHaveCount(1);
});

it('clears collected data', function () {
    $this->spy->getCollector()->collect('SELECT * FROM users', [], 5.0, 'mysql');
    expect($this->spy->getQueryCount())->toBe(1);

    $this->spy->clear();
    expect($this->spy->getQueryCount())->toBe(0);
});

it('provides access to collector and detector', function () {
    expect($this->spy->getCollector())->toBeInstanceOf(QueryCollector::class);
    expect($this->spy->getDetector())->toBeInstanceOf(NPlusOneDetector::class);
});

it('returns analyse results', function () {
    $this->spy->getCollector()->collect('SELECT * FROM users', [], 5.0, 'mysql');

    $results = $this->spy->analyse();

    expect($results)->toHaveKey('total_queries');
    expect($results)->toHaveKey('total_time');
    expect($results)->toHaveKey('n_plus_one_issues');
    expect($results)->toHaveKey('duplicate_groups');
    expect($results)->toHaveKey('slow_queries');
    expect($results)->toHaveKey('queries');
    expect($results['total_queries'])->toBe(1);
});
