<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy\Console\Commands;

use EloquentQuerySpy\EloquentQuerySpy\QuerySpy;
use Illuminate\Console\Command;

class SpyAnalyseCommand extends Command
{
    protected $signature = 'spy:analyse
        {url? : The URL or route name to analyse}
        {--method=GET : HTTP method for the request}
        {--params= : JSON parameters to send with the request}
        {--threshold= : N+1 detection threshold}
        {--json : Output as JSON}';

    protected $description = 'Analyse database queries for a given route or the current request';

    public function handle(QuerySpy $spy): int
    {
        $url = $this->argument('url');

        if (is_string($url) && $url !== '') {
            return $this->analyseRoute($spy, $url);
        }

        return $this->showCurrent($spy);
    }

    private function analyseRoute(QuerySpy $spy, string $url): int
    {
        $method = $this->option('method');

        if (! is_string($method)) {
            $method = 'GET';
        }

        $method = strtoupper($method);

        $params = $this->parseParams();

        $threshold = $this->option('threshold');

        if (is_numeric($threshold)) {
            $spy->getDetector()->setThreshold((int) $threshold);
        }

        app('router')->dispatchToRoute(app('request'));

        $spy->boot();

        $results = $spy->analyse();

        if ($this->option('json')) {
            $output = json_encode($results, JSON_PRETTY_PRINT);
            $this->output->writeln($output !== false ? $output : '{}');

            return self::SUCCESS;
        }

        $this->renderAnalyseResults($results);

        return self::SUCCESS;
    }

    private function showCurrent(QuerySpy $spy): int
    {
        $spy->boot();

        $results = $spy->analyse();

        if ($this->option('json')) {
            $output = json_encode($results, JSON_PRETTY_PRINT);
            $this->output->writeln($output !== false ? $output : '{}');

            return self::SUCCESS;
        }

        $this->renderAnalyseResults($results);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $results
     */
    private function renderAnalyseResults(array $results): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>Eloquent Query Spy Report</>', '');
        $this->newLine();

        $this->components->twoColumnDetail('Total Queries', (string) $results['total_queries']);
        $this->components->twoColumnDetail('Total Time', sprintf('%.2f ms', $results['total_time']));
        $this->components->twoColumnDetail('Duplicate Groups', (string) $results['duplicate_groups']);
        $this->components->twoColumnDetail('Slow Queries (>100ms)', (string) $results['slow_queries']);
        $this->newLine();

        if (! empty($results['n_plus_one_issues'])) {
            /** @var list<array<string, mixed>> $issues */
            $issues = $results['n_plus_one_issues'];
            $this->renderNPlusOneIssues($issues);
        }

        if (! empty($results['queries'])) {
            /** @var list<array<string, mixed>> $queries */
            $queries = $results['queries'];
            $this->renderQueries($queries);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function renderNPlusOneIssues(array $issues): void
    {
        $this->components->warn('N+1 Query Issues Detected');
        $this->newLine();

        foreach ($issues as $i => $issue) {
            $num = $i + 1;
            $this->line(sprintf('  <fg=red>⚠ Issue #%d:</> <options=bold>%s</>', $num, $issue['relation'] ?? 'unknown'));
            $this->line(sprintf('    Table:    <fg=yellow>%s</>', $issue['table']));
            $this->line(sprintf('    Count:    <fg=yellow>%d</> similar queries', $issue['count']));
            $this->line(sprintf('    File:     <fg=blue>%s:%d</>', $issue['file'], $issue['line']));

            if (! empty($issue['suggested_with'])) {
                $this->line(sprintf('    <fg=green>💡 Fix:</> Add <options=bold>%s</> to your query', $issue['suggested_with']));
            }

            if (! empty($issue['parent_sql'])) {
                $this->line(sprintf('    Parent:   <fg=gray>%s</>', $issue['parent_sql']));
            }

            $this->newLine();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $queries
     */
    private function renderQueries(array $queries): void
    {
        $this->components->info('Query Log');
        $this->newLine();

        $rows = [];
        foreach ($queries as $i => $query) {
            $time = sprintf('%.2fms', $query['time']);
            $timeFormatted = ($query['time'] ?? 0) > 100
                ? "<fg=red>$time</>"
                : "<fg=green>$time</>";

            $location = isset($query['file'])
                ? sprintf('<fg=blue>%s:%d</>', $query['file'], $query['line'] ?? 0)
                : '<fg=gray>unknown</>';

            $rows[] = [
                (string) ($i + 1),
                $location,
                $timeFormatted,
                $query['connection'] ?? 'default',
                substr($query['sql'] ?? '', 0, 120),
            ];
        }

        if (! empty($rows)) {
            $this->table(
                ['#', 'Location', 'Time', 'Connection', 'SQL'],
                $rows,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseParams(): array
    {
        $params = $this->option('params');

        if (! is_string($params) || $params === '') {
            return [];
        }

        $decoded = json_decode($params, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            $this->components->error('Invalid JSON for --params option');

            return [];
        }

        return $decoded;
    }
}
