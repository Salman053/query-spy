<?php

return [

    'enabled' => true,

    'n_plus_one' => [
        'enabled' => true,
        'threshold' => 3,
    ],

    'slow_query_threshold' => 100,

    'exclude' => [
        'patterns' => [
            '%information_schema%',
            '%performance_schema%',
            '%telescope_entries%',
            '%telescope_monitoring%',
        ],
        'tables' => [],
    ],

    'collect' => [
        'backtrace' => true,
        'bindings' => true,
        'memory' => true,
    ],

    'debugbar' => [
        'enabled' => true,
    ],

    'middleware' => [
        'enabled' => false,
        'output' => 'console',
    ],

];
