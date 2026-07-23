<div align="center">
    <h1>Eloquent Query Spy</h1>
    <p>Real-time database query debugging with N+1 detection for Laravel.</p>
</div>

<p align="center">
    <a href="https://packagist.org/packages/muhammad-salman-khan/eloquent-query-spy"><img src="https://img.shields.io/packagist/v/muhammad-salman-khan/eloquent-query-spy.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/muhammad-salman-khan/eloquent-query-spy"><img src="https://img.shields.io/packagist/php-v/muhammad-salman-khan/eloquent-query-spy.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://badge.laravel.cloud"><img src="https://badge.laravel.cloud/badge/muhammad-salman-khan/eloquent-query-spy?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/muhammad-salman-khan/eloquent-query-spy/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/muhammad-salman-khan/eloquent-query-spy/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/muhammad-salman-khan/eloquent-query-spy"><img src="https://img.shields.io/packagist/dt/muhammad-salman-khan/eloquent-query-spy.svg?style=flat-square" alt="Total Downloads"></a>
</p>

---

**Eloquent Query Spy** intercepts every database query during the request lifecycle, logs the exact file and line where the query was triggered, and intelligently detects N+1 issues — suggesting the correct `->with()` statement to fix them.

## Installation

```bash
composer require muhammad-salman-khan/eloquent-query-spy
```

Laravel will auto-discover the service provider. Publish the config (optional):

```bash
php artisan vendor:publish --tag="eloquent-query-spy-config"
```

## Quick Start

Use the facade anywhere in your code:

```php
use EloquentQuerySpy;

EloquentQuerySpy::boot();

// Your Eloquent queries...
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // triggers N+1
}

// Analyse results
$report = EloquentQuerySpy::analyse();
// or use the CLI:
// php artisan spy:report
```

## Features

### Query Interception & Backtrace Tracking

Every query is captured with its exact origin:

```php
EloquentQuerySpy::boot();

User::find(1);

$queries = EloquentQuerySpy::getQueries();
// Each query includes: sql, bindings, time, file, line, caller, hash
```

### N+1 Detection

Repeated similar SELECT queries are automatically flagged:

```php
EloquentQuerySpy::getDetector()->setThreshold(3);
EloquentQuerySpy::boot();

$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // lazy-loaded in a loop
}

$issues = EloquentQuerySpy::getNPlusOneIssues();
foreach ($issues as $issue) {
    echo $issue->relation;       // "posts"
    echo $issue->count;          // number of repeated queries
    echo $issue->suggestedFix(); // "->with('posts')"
    echo "$issue->file:$issue->line"; // where to add it
}
```

### Duplicate & Slow Query Detection

```php
// Duplicate queries (same SQL pattern)
$duplicates = EloquentQuerySpy::getDuplicates();

// Slow queries (>100ms by default)
$slow = EloquentQuerySpy::getSlowQueries();
```

## CLI Commands

```bash
# Show a performance report for the current request
php artisan spy:report

# Analyse a specific route
php artisan spy:analyse /users

# JSON output for CI or tooling
php artisan spy:report --json

# Clear collected data
php artisan spy:clear
```

The `spy:report` command displays:

```
📊 Query Performance Report
  Total Queries Executed  12
  Total Query Time        45.23 ms
  N+1 Issues Found        1
  Duplicate Query Groups  2
  Slow Queries            0

⚠ posts
   Table: posts | Count: 5
   💡 Fix: ->with('posts')
```

## DebugBar Integration

If [`barryvdh/laravel-debugbar`](https://github.com/barryvdh/laravel-debugbar) is installed, Query Spy automatically adds a collector with query count, N+1 warnings, and per-query details.

## Configuration

Publish the config to customize behavior:

```php
// config/eloquent-query-spy.php
return [
    'n_plus_one' => [
        'enabled' => true,
        'threshold' => 3,            // queries below this count are ignored
    ],
    'slow_query_threshold' => 100,   // milliseconds
    'exclude' => [
        'patterns' => [
            '%telescope_entries%',   // ignore telescope queries
        ],
    ],
    'debugbar' => [
        'enabled' => true,
    ],
];
```

## Usage Examples

### Middleware (opt-in)

Register in `bootstrap/app.php` or `Http/Kernel.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias('query-spy', \EloquentQuerySpy\Http\Middleware\SpyMiddleware::class);
})
```

Or enable globally via config:

```php
'middleware' => [
    'enabled' => true,
    'output' => 'response', // injects an HTML overlay on pages with N+1 issues
],
```

### Programmatic Analysis

```php
use EloquentQuerySpy;

EloquentQuerySpy::boot();

// Run your queries
User::with('posts')->get();

// Get full analysis
$report = EloquentQuerySpy::analyse();
// [
//   'total_queries' => 2,
//   'n_plus_one_issues' => [],
//   'duplicate_groups' => 0,
//   'slow_queries' => 0,
// ]
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## License

Eloquent Query Spy is open-sourced software licensed under the [MIT license](LICENSE.md).
