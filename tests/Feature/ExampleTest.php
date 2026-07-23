<?php

declare(strict_types=1);

use EloquentQuerySpy\EloquentQuerySpy\QuerySpy;

it('resolves the QuerySpy singleton', function () {
    expect(app(QuerySpy::class))->toBeInstanceOf(QuerySpy::class);
});

it('returns the same instance from the container', function () {
    expect(app(QuerySpy::class))->toBe(app(QuerySpy::class));
});

it('merges the package config', function () {
    expect(config('eloquent-query-spy.enabled'))->toBeTrue();
});
