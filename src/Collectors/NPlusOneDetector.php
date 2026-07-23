<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy\Collectors;

use EloquentQuerySpy\EloquentQuerySpy\Data\NPlusOneIssue;
use EloquentQuerySpy\EloquentQuerySpy\Data\QueryExecution;
use Illuminate\Support\Str;

class NPlusOneDetector
{
    private int $threshold;

    public function __construct(
        private readonly QueryHasher $hasher,
        int $threshold = 3,
    ) {
        $this->threshold = $threshold;
    }

    public function setThreshold(int $threshold): void
    {
        $this->threshold = $threshold;
    }

    public function getThreshold(): int
    {
        return $this->threshold;
    }

    /**
     * @param  list<QueryExecution>  $queries
     * @return list<NPlusOneIssue>
     */
    public function detect(array $queries): array
    {
        $selectQueries = $this->filterSelectQueries($queries);

        if (count($selectQueries) < $this->threshold) {
            return [];
        }

        $grouped = $this->groupByHash($selectQueries);

        $issues = [];

        foreach ($grouped as $hash => $group) {
            if (count($group) < $this->threshold) {
                continue;
            }

            $issue = $this->buildIssue($hash, $group, $queries);
            $issues[] = $issue;
        }

        return $issues;
    }

    /**
     * @param  list<QueryExecution>  $queries
     * @return list<QueryExecution>
     */
    private function filterSelectQueries(array $queries): array
    {
        return array_values(array_filter(
            $queries,
            fn (QueryExecution $q) => str_starts_with(strtoupper(trim($q->sql)), 'SELECT'),
        ));
    }

    /**
     * @param  list<QueryExecution>  $queries
     * @return array<string, list<QueryExecution>>
     */
    private function groupByHash(array $queries): array
    {
        $grouped = [];

        foreach ($queries as $query) {
            $grouped[$query->hash][] = $query;
        }

        return $grouped;
    }

    /**
     * @param  list<QueryExecution>  $group
     * @param  list<QueryExecution>  $allQueries
     */
    private function buildIssue(string $hash, array $group, array $allQueries): NPlusOneIssue
    {
        $first = $group[0];
        $table = $this->hasher->extractTable($first->sql) ?? 'unknown';
        $relation = $this->guessRelationship($table, $first->sql);

        $parentQuery = $this->findParentQuery($hash, $allQueries);

        $suggestedWith = $this->buildSuggestion($relation, $parentQuery);

        $targetFile = $parentQuery !== null ? ($parentQuery->file ?? $first->file ?? 'unknown') : ($first->file ?? 'unknown');
        $targetLine = $parentQuery !== null ? ($parentQuery->line ?? $first->line ?? 0) : ($first->line ?? 0);

        return new NPlusOneIssue(
            sql: $first->sql,
            table: $table,
            relation: $relation,
            count: count($group),
            file: $targetFile,
            line: $targetLine,
            suggestedWith: $suggestedWith,
            queries: $group,
            parentQuery: $parentQuery,
        );
    }

    private function guessRelationship(string $table, string $sql): string
    {
        $singular = Str::singular($table);

        if (preg_match('/\.`?'.preg_quote($singular, '/').'_id`?\s*=\s*`?\w+`?\.`?id`?/i', $sql)) {
            return $singular;
        }

        if (preg_match('/`?'.preg_quote($singular, '/').'_id`?\s*in\s*\(/i', $sql)) {
            return $singular;
        }

        preg_match_all('/`?(\w+)_id`?\s*(?:in|=)/i', $sql, $matches);

        if (! empty($matches[1])) {
            return $matches[1][0];
        }

        return $singular;
    }

    /**
     * @param  list<QueryExecution>  $allQueries
     */
    private function findParentQuery(string $nestedHash, array $allQueries): ?QueryExecution
    {
        $selects = $this->filterSelectQueries($allQueries);

        if (count($selects) < 2) {
            return null;
        }

        $firstSelectIndex = null;

        foreach ($selects as $index => $query) {
            if ($query->hash === $nestedHash) {
                $firstSelectIndex = $index;

                break;
            }
        }

        if ($firstSelectIndex === null || $firstSelectIndex === 0) {
            return null;
        }

        return $selects[$firstSelectIndex - 1];
    }

    private function buildSuggestion(?string $relation, ?QueryExecution $parentQuery): string
    {
        if ($relation === null) {
            return 'Add eager loading for the detected relationship';
        }

        return sprintf('->with(\'%s\')', $relation);
    }
}
