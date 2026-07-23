<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy\Console\Commands;

use EloquentQuerySpy\EloquentQuerySpy\Data\NPlusOneIssue;
use EloquentQuerySpy\EloquentQuerySpy\Data\QueryExecution;
use EloquentQuerySpy\EloquentQuerySpy\QuerySpy;
use Illuminate\Console\Command;

class SpyReportCommand extends Command
{
    protected $signature = 'spy:report
        {--json : Output as JSON}
        {--slow-threshold=100 : Threshold for slow queries in milliseconds}';

    protected $description = 'Display a comprehensive query performance report';

    public function handle(QuerySpy $spy): int
    {
        $spy->boot();

        $queries = $spy->getQueries();
        $nPlusOne = $spy->getNPlusOneIssues();
        $duplicates = $spy->getDuplicates();
        $slowThreshold = (int) ($this->option('slow-threshold') ?? 100);
        $slowQueries = $spy->getSlowQueries($slowThreshold);

        if ($this->option('json')) {
            $output = json_encode([
                'summary' => [
                    'total_queries' => count($queries),
                    'total_time' => $spy->getTotalTime(),
                    'n_plus_one_count' => count($nPlusOne),
                    'duplicate_group_count' => count($duplicates),
                    'slow_query_count' => count($slowQueries),
                ],
                'n_plus_one_issues' => array_map(fn (NPlusOneIssue $i) => $i->toArray(), $nPlusOne),
                'duplicate_queries' => array_map(
                    fn (array $group) => array_map(fn (QueryExecution $q) => $q->toArray(), $group),
                    $duplicates,
                ),
                'slow_queries' => array_map(fn (QueryExecution $q) => $q->toArray(), $slowQueries),
            ], JSON_PRETTY_PRINT);

            $this->output->writeln($output !== false ? $output : '{}');

            return self::SUCCESS;
        }

        $this->renderSummary($queries, $nPlusOne, $duplicates, $slowQueries);

        return self::SUCCESS;
    }

    /**
     * @param  list<QueryExecution>  $queries
     * @param  list<NPlusOneIssue>  $nPlusOne
     * @param  list<list<QueryExecution>>  $duplicates
     * @param  list<QueryExecution>  $slowQueries
     */
    private function renderSummary(array $queries, array $nPlusOne, array $duplicates, array $slowQueries): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>📊 Query Performance Report</>', '');
        $this->newLine();

        $this->components->twoColumnDetail('Total Queries Executed', (string) count($queries));
        $this->components->twoColumnDetail('Total Query Time', sprintf('%.2f ms', array_sum(array_map(fn (QueryExecution $q) => $q->time, $queries))));
        $this->components->twoColumnDetail('N+1 Issues Found', (string) count($nPlusOne));
        $this->components->twoColumnDetail('Duplicate Query Groups', (string) count($duplicates));
        $this->components->twoColumnDetail('Slow Queries', (string) count($slowQueries));
        $this->newLine();

        if (! empty($nPlusOne)) {
            $this->components->warn('N+1 Issues');
            $this->newLine();

            foreach ($nPlusOne as $issue) {
                $this->line(sprintf('  <fg=red>⚠ %s</>', $issue->relation ?? 'unknown'));
                $this->line(sprintf('     Table: %s | Count: %d | %s:%d', $issue->table, $issue->count, $issue->file, $issue->line));
                $this->line(sprintf('     <fg=green>💡 Fix:</> %s', $issue->suggestedFix()));
                $this->newLine();
            }
        }

        if (! empty($slowQueries)) {
            $this->components->info('Slow Queries');
            $this->newLine();

            $rows = [];
            foreach ($slowQueries as $query) {
                $rows[] = [
                    sprintf('%.2f ms', $query->time),
                    $query->file && $query->line ? "$query->file:$query->line" : 'unknown',
                    substr($query->sql, 0, 100),
                ];
            }

            $this->table(
                ['Time', 'Location', 'SQL'],
                $rows,
            );
        }

        if (! empty($queries)) {
            $this->components->info('All Queries');
            $this->newLine();

            $rows = [];
            foreach ($queries as $i => $query) {
                $rows[] = [
                    (string) ($i + 1),
                    sprintf('%.2f ms', $query->time),
                    $query->file && $query->line ? "$query->file:$query->line" : 'unknown',
                    substr($query->sql, 0, 100),
                ];
            }

            $this->table(
                ['#', 'Time', 'Location', 'SQL'],
                $rows,
            );
        }
    }
}
