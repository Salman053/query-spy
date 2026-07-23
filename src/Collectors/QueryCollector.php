<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy\Collectors;

use EloquentQuerySpy\EloquentQuerySpy\Data\QueryExecution;

class QueryCollector
{
    /** @var list<QueryExecution> */
    private array $queries = [];

    /** @var array<string, int> */
    private array $queryCounts = [];

    /** @var list<string> */
    private array $excludedPatterns = [];

    public function __construct(
        private readonly QueryHasher $hasher,
        private readonly BacktraceParser $backtraceParser,
    ) {}

    /**
     * @param  list<mixed>  $bindings
     */
    public function collect(
        string $sql,
        array $bindings,
        float $time,
        string $connection = 'default',
    ): ?QueryExecution {
        if ($this->isExcluded($sql, $connection)) {
            return null;
        }

        $hash = $this->hasher->hash($sql);
        $this->queryCounts[$hash] = ($this->queryCounts[$hash] ?? 0) + 1;

        [$file, $line, $caller] = $this->backtraceParser->parse(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $memoryBefore = memory_get_usage(true);

        $query = new QueryExecution(
            sql: $sql,
            bindings: $bindings,
            time: $time,
            connection: $connection,
            file: $file,
            line: $line,
            caller: $caller,
            hash: $hash,
            memoryBefore: $memoryBefore,
            memoryAfter: memory_get_usage(true),
            occurrence: $this->queryCounts[$hash],
        );

        $this->queries[] = $query;

        return $query;
    }

    /** @return list<QueryExecution> */
    public function getQueries(): array
    {
        return $this->queries;
    }

    public function count(): int
    {
        return count($this->queries);
    }

    public function totalTime(): float
    {
        return array_sum(array_map(fn (QueryExecution $q) => $q->time, $this->queries));
    }

    public function clear(): void
    {
        $this->queries = [];
        $this->queryCounts = [];
    }

    /** @return list<list<QueryExecution>> */
    public function getDuplicateQueries(): array
    {
        $grouped = [];

        foreach ($this->queries as $query) {
            $grouped[$query->hash][] = $query;
        }

        return array_values(array_filter($grouped, fn (array $group) => count($group) > 1));
    }

    /** @return list<QueryExecution> */
    public function getSlowQueries(int $thresholdMs = 100): array
    {
        return array_values(array_filter(
            $this->queries,
            fn (QueryExecution $q) => $q->isSlow($thresholdMs),
        ));
    }

    /** @param list<string> $patterns */
    public function setExcludedPatterns(array $patterns): void
    {
        $this->excludedPatterns = $patterns;
    }

    /** @return list<QueryExecution> */
    public function getQueryByHash(string $hash): array
    {
        return array_values(array_filter(
            $this->queries,
            fn (QueryExecution $q) => $q->hash === $hash,
        ));
    }

    /** @return array<string, int> */
    public function getQueryCounts(): array
    {
        return $this->queryCounts;
    }

    public function resetQueryCounts(): void
    {
        $this->queryCounts = [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'total_queries' => $this->count(),
            'total_time' => $this->totalTime(),
            'queries' => array_map(fn (QueryExecution $q) => $q->toArray(), $this->queries),
            'duplicates' => array_map(
                fn (array $group) => array_map(fn (QueryExecution $q) => $q->toArray(), $group),
                $this->getDuplicateQueries(),
            ),
        ];
    }

    private function isExcluded(string $sql, string $connection): bool
    {
        foreach ($this->excludedPatterns as $pattern) {
            if (str_contains($sql, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
