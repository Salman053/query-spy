<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy\Http\Middleware;

use Closure;
use EloquentQuerySpy\EloquentQuerySpy\Data\NPlusOneIssue;
use EloquentQuerySpy\EloquentQuerySpy\QuerySpy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SpyMiddleware
{
    public function __construct(
        private readonly QuerySpy $spy,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $this->spy->boot();

        if ($this->spy->isBooted()) {
            $this->spy->startListening();
        }

        $response = $next($request);

        $nPlusOne = $this->spy->getNPlusOneIssues();

        if (! empty($nPlusOne) && $this->shouldNotify($request)) {
            $this->attachNPlusOneWarning($response, $nPlusOne);
        }

        return $response;
    }

    private function shouldNotify(Request $request): bool
    {
        $allowedOutput = config('eloquent-query-spy.middleware.output', 'console');

        if ($allowedOutput === 'none') {
            return false;
        }

        return true;
    }

    /**
     * @param  list<NPlusOneIssue>  $issues
     */
    private function attachNPlusOneWarning(mixed $response, array $issues): void
    {
        if ($response instanceof Response && ! headers_sent()) {
            $response->headers->set('X-Query-Spy-N-Plus-One', (string) count($issues));
            $response->headers->set('X-Query-Spy-Total-Queries', (string) $this->spy->getQueryCount());
            $response->headers->set('X-Query-Spy-Total-Time', (string) $this->spy->getTotalTime());
        }

        if ($this->spy->getQueryCount() > 0 && config('eloquent-query-spy.middleware.output', 'console') === 'response') {
            if ($response instanceof Response) {
                $existing = $response->getContent();
                $spyOutput = $this->renderNPlusOneHtml($issues);
                $response->setContent(($existing !== false ? $existing : '').$spyOutput);
            }
        }
    }

    /**
     * @param  list<NPlusOneIssue>  $issues
     */
    private function renderNPlusOneHtml(array $issues): string
    {
        $html = '<div style="position:fixed;bottom:0;right:0;z-index:99999;font-family:monospace;font-size:12px;background:#1a1a2e;color:#eee;padding:16px;border-radius:8px 0 0 0;box-shadow:0 0 20px rgba(0,0,0,.5);max-width:600px;max-height:400px;overflow-y:auto;border-top:3px solid #e74c3c;">';
        $html .= '<div style="font-weight:bold;margin-bottom:8px;color:#e74c3c;">⚠ Query Spy - N+1 Issues Detected</div>';

        foreach ($issues as $issue) {
            $html .= '<div style="margin-bottom:12px;padding:8px;background:#16213e;border-radius:4px;border-left:3px solid #e74c3c;">';
            $html .= '<div style="color:#f39c12;">Relation: <strong>'.e($issue->relation ?? 'unknown').'</strong></div>';
            $html .= '<div style="color:#7f8c8d;font-size:11px;">Table: '.e($issue->table).' | '.$issue->count.' queries</div>';
            $html .= '<div style="color:#7f8c8d;font-size:11px;">File: '.e($issue->file).':'.$issue->line.'</div>';
            $html .= '<div style="color:#2ecc71;margin-top:4px;">💡 Fix: <code style="background:#0f3460;padding:2px 4px;border-radius:2px;">'.e($issue->suggestedFix()).'</code></div>';
            $html .= '</div>';
        }

        $html .= '<div style="font-size:11px;color:#7f8c8d;margin-top:8px;">Total: '.count($issues).' issue(s) | Queries: '.$this->spy->getQueryCount().' | Time: '.sprintf('%.2f', $this->spy->getTotalTime()).'ms</div>';
        $html .= '</div>';

        return $html;
    }
}
