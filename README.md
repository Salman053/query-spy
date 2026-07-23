# Eloquent Query Spy

Real-time database query debugging with N+1 detection for Laravel. Captures every query with its exact file and line origin, detects N+1 patterns with suggested eager-loading fixes, and tracks duplicate/slow queries.

## Installation

```bash
composer require muhammad-salman-khan/laravel-query-spy
```

Laravel auto-discovers the service provider. Optionally publish the config:

```bash
php artisan vendor:publish --tag="eloquent-query-spy-config"
```

## Quick Start

Use the `QuerySpy` facade (aliased automatically via `composer.json`):

```php
use QuerySpy;

QuerySpy::boot();

$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // triggers N+1
}

$report = QuerySpy::analyse();
```

Or via dependency injection:

```php
use EloquentQuerySpy\EloquentQuerySpy\QuerySpy;

Route::get('/users', function (QuerySpy $spy) {
    $spy->boot();

    $users = User::all();
    foreach ($users as $user) {
        $user->posts->each(fn ($post) => $post->comments);
    }

    return response()->json($spy->analyse());
});
```

## Features

### Query Interception

Every query executed through Laravel's DB facade or Eloquent is captured with backtrace info:

```php
QuerySpy::boot();

User::find(1);

$queries = QuerySpy::getQueries();
// $queries[0]->sql       // "select * from "users" where "id" = ? limit 1"
// $queries[0]->bindings  // [1]
// $queries[0]->time      // 2.45 (ms)
// $queries[0]->file      // "app/Http/Controllers/UserController.php"
// $queries[0]->line      // 42
// $queries[0]->caller    // "UserController::show"
// $queries[0]->hash      // "ab12cd34"
// $queries[0]->occurrence // 1
```

The backtrace parser skips vendor frames and spy internals to report the actual application call site.

### N+1 Detection

Repeated similar SELECT queries are flagged as N+1 issues with the suggested fix:

```php
QuerySpy::getDetector()->setThreshold(3);
QuerySpy::boot();

$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count();
}

$issues = QuerySpy::getNPlusOneIssues();

foreach ($issues as $issue) {
    echo $issue->relation;       // "posts"
    echo $issue->count;          // number of repeated queries
    echo $issue->suggestedFix(); // "->with('posts')"
    echo "$issue->file:$issue->line"; // file and line to fix
    echo $issue->parentQuery->sql;    // the initial query before N+1
    echo $issue->parentQuery->file;   // where the parent query was triggered
}
```

**How it works:** The detector groups SELECT queries by their table. If a table has repeated identical queries (after normalizing bindings) above the threshold, it reports the table name, relation name, suggested `->with()` call, and the suspicious file/line.

### Duplicate Query Detection

Same SQL pattern executed multiple times in a request:

```php
$duplicates = QuerySpy::getDuplicates();
// [[$query1, $query2], [$query3, $query4, $query5]]
```

### Slow Query Detection

Queries exceeding a threshold in milliseconds:

```php
$slow = QuerySpy::getSlowQueries(thresholdMs: 200);
// Only queries with time > 200ms
```

### Full Analysis Report

```php
$report = QuerySpy::analyse();
// [
//   'total_queries'     => 12,
//   'total_time'        => 45.23,
//   'n_plus_one_issues' => [...],
//   'duplicate_groups'  => 2,
//   'slow_queries'      => 0,
//   'queries'           => [...],
// ]
```

## CLI Commands

```bash
# Report for the current request
php artisan spy:report

# Analyse a specific route by making a real HTTP request
php artisan spy:analyse /users

# JSON output (useful for CI or tooling)
php artisan spy:report --json
php artisan spy:analyse /users --json

# Clear collected data between requests
php artisan spy:clear
```

The report displays total queries, time, N+1 issues, duplicate groups, slow queries, and per-issue details with suggested fixes.

## Middleware

Register the middleware alias in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias('query-spy', \EloquentQuerySpy\Http\Middleware\SpyMiddleware::class);
})
```

Or enable globally via config with console output:

```php
// config/eloquent-query-spy.php
'middleware' => [
    'enabled' => true,
    'output' => 'console',
],
```

## DebugBar Integration

If [`barryvdh/laravel-debugbar`](https://github.com/barryvdh/laravel-debugbar) is installed, Query Spy automatically registers a collector showing query count, N+1 warnings, and per-query details. Disable via config:

```php
'debugbar' => [
    'enabled' => false,
],
```

## Configuration

```php
// config/eloquent-query-spy.php
return [

    'enabled' => true,

    'n_plus_one' => [
        'enabled' => true,
        'threshold' => 3,         // ignore patterns below this count
    ],

    'slow_query_threshold' => 100, // milliseconds

    'exclude' => [
        'patterns' => [
            '%telescope_entries%',
            '%telescope_monitoring%',
        ],
        'tables' => [],
    ],

    'collect' => [
        'backtrace' => true,       // capture file/line for each query
        'bindings' => true,        // include query bindings
        'memory' => true,          // track memory before/after
    ],

    'debugbar' => [
        'enabled' => true,
    ],

    'middleware' => [
        'enabled' => false,
        'output' => 'console',
    ],

];
```

## API Reference

All methods are available via the `QuerySpy` facade or by resolving `EloquentQuerySpy\EloquentQuerySpy\QuerySpy` from the container.

| Method | Returns | Description |
|--------|---------|-------------|
| `boot()` | `void` | Start listening for queries (idempotent) |
| `stopListening()` | `void` | Stop listening |
| `isListening()` | `bool` | Check if actively listening |
| `getQueries()` | `QueryExecution[]` | All captured queries |
| `getQueryCount()` | `int` | Total query count |
| `getTotalTime()` | `float` | Total query time in ms |
| `getNPlusOneIssues()` | `NPlusOneIssue[]` | Detected N+1 issues |
| `getDuplicates()` | `QueryExecution[][]` | Groups of duplicate queries |
| `getSlowQueries(int $thresholdMs)` | `QueryExecution[]` | Slow queries |
| `clear()` | `void` | Reset all collected data |
| `analyse()` | `array` | Full analysis report |
| `getCollector()` | `QueryCollector` | Raw collector instance |
| `getDetector()` | `NPlusOneDetector` | Detector instance (for threshold config) |

## Development & Workbench

This package uses [Orchestra Testbench](https://github.com/orchestral/testbench) for development. Run the workbench server to test in a browser:

```bash
composer serve
```

Available workbench routes:
- `/spy` — demonstrates N+1 with lazy loading
- `/spy-eager` — same queries with eager loading (zero issues)

Run tests:

```bash
composer test          # full validation (static analysis + lint + type coverage + unit)
composer test:unit     # Pest tests only
composer lint:check    # Pint formatting check
composer analyse       # PHPStan static analysis
composer build         # rebuild workbench migrations
```

## Changelog

See [CHANGELOG](CHANGELOG.md) for release history.

## Contributing

See [CONTRIBUTING](.github/CONTRIBUTING.md) for guidelines.

## License

MIT — see [LICENSE](LICENSE.md).
