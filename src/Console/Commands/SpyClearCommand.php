<?php

declare(strict_types=1);

namespace EloquentQuerySpy\EloquentQuerySpy\Console\Commands;

use EloquentQuerySpy\EloquentQuerySpy\QuerySpy;
use Illuminate\Console\Command;

class SpyClearCommand extends Command
{
    protected $signature = 'spy:clear';

    protected $description = 'Clear all collected query data';

    public function handle(QuerySpy $spy): int
    {
        $spy->clear();

        $this->components->info('Query Spy data has been cleared.');

        return self::SUCCESS;
    }
}
