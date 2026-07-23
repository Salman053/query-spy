<?php

declare(strict_types=1);

arch()->preset()->php()->ignoring([
    'debug_backtrace',
]);

arch()->preset()->security()->ignoring([
    'md5',
]);

arch('will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('EloquentQuerySpy\EloquentQuerySpy')
    ->toUseStrictTypes();
