<?php

declare(strict_types=1);

use EloquentQuerySpy\EloquentQuerySpy\Collectors\BacktraceParser;
use EloquentQuerySpy\EloquentQuerySpy\Collectors\NPlusOneDetector;
use EloquentQuerySpy\EloquentQuerySpy\Collectors\QueryCollector;
use EloquentQuerySpy\EloquentQuerySpy\Collectors\QueryHasher;
use EloquentQuerySpy\EloquentQuerySpy\QuerySpy;

it('registers the QuerySpy singleton', function () {
    expect(app(QuerySpy::class))->toBeInstanceOf(QuerySpy::class);
    expect(app(QuerySpy::class))->toBe(app(QuerySpy::class));
});

it('registers the QueryHasher singleton', function () {
    expect(app(QueryHasher::class))->toBeInstanceOf(QueryHasher::class);
});

it('registers the BacktraceParser singleton', function () {
    expect(app(BacktraceParser::class))->toBeInstanceOf(BacktraceParser::class);
});

it('registers the QueryCollector singleton', function () {
    expect(app(QueryCollector::class))->toBeInstanceOf(QueryCollector::class);
});

it('registers the NPlusOneDetector singleton', function () {
    expect(app(NPlusOneDetector::class))->toBeInstanceOf(NPlusOneDetector::class);
});

it('merges the package config', function () {
    expect(config('eloquent-query-spy.enabled'))->toBeTrue();
    expect(config('eloquent-query-spy.n_plus_one.threshold'))->toBe(3);
    expect(config('eloquent-query-spy.slow_query_threshold'))->toBe(100);
});

it('registers the spy:analyse command', function () {
    $this->artisan('spy:analyse')
        ->doesntExpectOutputToContain('N+1 Query Issues Detected')
        ->assertSuccessful();
});

it('registers the spy:clear command', function () {
    $this->artisan('spy:clear')
        ->expectsOutputToContain('Query Spy data has been cleared.')
        ->assertSuccessful();
});

it('registers the spy:report command', function () {
    $this->artisan('spy:report')
        ->expectsOutputToContain('Query Performance Report')
        ->assertSuccessful();
});
