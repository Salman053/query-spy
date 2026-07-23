<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void boot()
 * @method static void startListening()
 * @method static void stopListening()
 * @method static bool isListening()
 * @method static bool isBooted()
 * @method static \EloquentQuerySpy\EloquentQuerySpy\Collectors\QueryCollector getCollector()
 * @method static \EloquentQuerySpy\EloquentQuerySpy\Collectors\NPlusOneDetector getDetector()
 * @method static \EloquentQuerySpy\EloquentQuerySpy\Data\QueryExecution[] getQueries()
 * @method static int getQueryCount()
 * @method static float getTotalTime()
 * @method static \EloquentQuerySpy\EloquentQuerySpy\Data\NPlusOneIssue[] getNPlusOneIssues()
 * @method static \EloquentQuerySpy\EloquentQuerySpy\Data\QueryExecution[][] getDuplicates()
 * @method static \EloquentQuerySpy\EloquentQuerySpy\Data\QueryExecution[] getSlowQueries(int $thresholdMs = 100)
 * @method static void clear()
 * @method static array<string, mixed> analyse()
 *
 * @see \EloquentQuerySpy\EloquentQuerySpy\QuerySpy
 */
class QuerySpy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \EloquentQuerySpy\EloquentQuerySpy\QuerySpy::class;
    }
}
