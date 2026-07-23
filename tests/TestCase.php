<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy\Tests;

use EloquentQuerySpy\EloquentQuerySpy\EloquentQuerySpyServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            EloquentQuerySpyServiceProvider::class,
        ];
    }
}
