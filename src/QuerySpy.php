<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy;

use EloquentQuerySpy\EloquentQuerySpy\Collectors\NPlusOneDetector;
use EloquentQuerySpy\EloquentQuerySpy\Collectors\QueryCollector;
use EloquentQuerySpy\EloquentQuerySpy\Data\NPlusOneIssue;
use EloquentQuerySpy\EloquentQuerySpy\Data\QueryExecution;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

class QuerySpy
{
    private bool $listening = false;

    private bool $booted = false;

    public function __construct(
        private readonly QueryCollector $collector,
        private readonly NPlusOneDetector $detector,
    ) {}

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;
        $this->startListening();
    }

    public function startListening(): void
    {
        if ($this->listening) {
            return;
        }

        $this->listening = true;

        DB::listen(function (QueryExecuted $query) {
            $bindings = array_values($query->bindings);

            $this->collector->collect(
                sql: $query->sql,
                bindings: $bindings,
                time: $query->time,
                connection: $query->connectionName,
            );
        });
    }

    public function stopListening(): void
    {
        $this->listening = false;
    }

    public function isListening(): bool
    {
        return $this->listening;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function getCollector(): QueryCollector
    {
        return $this->collector;
    }

    public function getDetector(): NPlusOneDetector
    {
        return $this->detector;
    }

    /** @return list<QueryExecution> */
    public function getQueries(): array
    {
        return $this->collector->getQueries();
    }

    public function getQueryCount(): int
    {
        return $this->collector->count();
    }

    public function getTotalTime(): float
    {
        return $this->collector->totalTime();
    }

    /** @return list<NPlusOneIssue> */
    public function getNPlusOneIssues(): array
    {
        return $this->detector->detect($this->collector->getQueries());
    }

    /** @return list<list<QueryExecution>> */
    public function getDuplicates(): array
    {
        return $this->collector->getDuplicateQueries();
    }

    /** @return list<QueryExecution> */
    public function getSlowQueries(int $thresholdMs = 100): array
    {
        return $this->collector->getSlowQueries($thresholdMs);
    }

    public function clear(): void
    {
        $this->collector->clear();
    }

    /** @return array<string, mixed> */
    public function analyse(): array
    {
        return [
            'total_queries' => $this->getQueryCount(),
            'total_time' => $this->getTotalTime(),
            'n_plus_one_issues' => array_map(
                fn (NPlusOneIssue $issue) => $issue->toArray(),
                $this->getNPlusOneIssues(),
            ),
            'duplicate_groups' => count($this->getDuplicates()),
            'slow_queries' => count($this->getSlowQueries()),
            'queries' => array_map(fn (QueryExecution $q) => $q->toArray(), $this->getQueries()),
        ];
    }
}
