<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy;

use Barryvdh\Debugbar\LaravelDebugbar;
use EloquentQuerySpy\EloquentQuerySpy\Collectors\BacktraceParser;
use EloquentQuerySpy\EloquentQuerySpy\Collectors\NPlusOneDetector;
use EloquentQuerySpy\EloquentQuerySpy\Collectors\QueryCollector;
use EloquentQuerySpy\EloquentQuerySpy\Collectors\QueryHasher;
use EloquentQuerySpy\EloquentQuerySpy\Console\Commands\SpyAnalyseCommand;
use EloquentQuerySpy\EloquentQuerySpy\Console\Commands\SpyClearCommand;
use EloquentQuerySpy\EloquentQuerySpy\Console\Commands\SpyReportCommand;
use EloquentQuerySpy\EloquentQuerySpy\DebugBar\QuerySpyCollector;
use EloquentQuerySpy\EloquentQuerySpy\Http\Middleware\SpyMiddleware;
use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;

class QuerySpyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/eloquent-query-spy.php', 'eloquent-query-spy');

        $this->app->singleton(QueryHasher::class);
        $this->app->singleton(BacktraceParser::class);

        $this->app->singleton(QueryCollector::class, function (Container $app) {
            $collector = new QueryCollector(
                $app->make(QueryHasher::class),
                $app->make(BacktraceParser::class),
            );

            /** @var list<string> $patterns */
            $patterns = config('eloquent-query-spy.exclude.patterns', []);
            $collector->setExcludedPatterns($patterns);

            return $collector;
        });

        $this->app->singleton(NPlusOneDetector::class, function (Container $app) {
            $detector = new NPlusOneDetector(
                $app->make(QueryHasher::class),
                (int) config('eloquent-query-spy.n_plus_one.threshold', 3),
            );

            return $detector;
        });

        $this->app->singleton(QuerySpy::class, function (Container $app) {
            return new QuerySpy(
                $app->make(QueryCollector::class),
                $app->make(NPlusOneDetector::class),
            );
        });
    }

    public function boot(): void
    {
        $this->bootDebugBar();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/eloquent-query-spy.php' => config_path('eloquent-query-spy.php'),
            ], ['eloquent-query-spy', 'eloquent-query-spy-config']);
        }

        $this->registerMiddleware();

        $this->commands([
            SpyAnalyseCommand::class,
            SpyClearCommand::class,
            SpyReportCommand::class,
        ]);
    }

    private function bootDebugBar(): void
    {
        if (! class_exists(LaravelDebugbar::class)) {
            return;
        }

        if (! config('eloquent-query-spy.debugbar.enabled', true)) {
            return;
        }

        $this->app->booted(function (Container $app) {
            $debugbar = $app->make(LaravelDebugbar::class);

            $debugbar->addCollector(
                $app->make(QuerySpyCollector::class),
            );
        });
    }

    private function registerMiddleware(): void
    {
        if (! config('eloquent-query-spy.middleware.enabled', false)) {
            return;
        }

        $this->app->make('router')->aliasMiddleware('query-spy', SpyMiddleware::class);
    }
}
