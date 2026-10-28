<?php

return [

    'framework' => 'laravel',

    'paths' => [
        'src',
    ],

    'laravel' => [
        'routes' => [],
        'migrations' => ['database/migrations'],
    ],

    'rules' => [

        // Migrations here never use down().
        'SL215' => [
            'enabled' => false,
        ],

    ],

];
