<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy\DebugBar;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use EloquentQuerySpy\EloquentQuerySpy\QuerySpy;

class QuerySpyCollector extends DataCollector implements Renderable
{
    public function __construct(
        private readonly QuerySpy $spy,
    ) {}

    public function collect(): array
    {
        $queries = $this->spy->getQueries();
        $nPlusOne = $this->spy->getNPlusOneIssues();
        $duplicates = $this->spy->getDuplicates();
        $slowQueries = $this->spy->getSlowQueries();

        $queryData = [];
        foreach ($queries as $query) {
            $queryData[] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
                'connection' => $query->connection,
                'file' => $query->file,
                'line' => $query->line,
                'caller' => $query->caller,
                'occurrence' => $query->occurrence,
            ];
        }

        $nPlusOneData = [];
        foreach ($nPlusOne as $issue) {
            $nPlusOneData[] = [
                'table' => $issue->table,
                'relation' => $issue->relation,
                'count' => $issue->count,
                'file' => $issue->file,
                'line' => $issue->line,
                'suggestion' => $issue->suggestedFix(),
            ];
        }

        return [
            'count' => count($queries),
            'total_time' => $this->spy->getTotalTime(),
            'n_plus_one_count' => count($nPlusOne),
            'duplicate_groups' => count($duplicates),
            'slow_query_count' => count($slowQueries),
            'queries' => $queryData,
            'n_plus_one' => $nPlusOneData,
        ];
    }

    public function getName(): string
    {
        return 'eloquent_query_spy';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getWidgets(): array
    {
        return [
            'eloquent_query_spy' => [
                'icon' => 'database',
                'tooltip' => 'Query Spy',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'eloquent_query_spy',
                'default' => '{}',
            ],
            'eloquent_query_spy:badge' => [
                'map' => 'eloquent_query_spy.count',
                'default' => 0,
            ],
        ];
    }
}
