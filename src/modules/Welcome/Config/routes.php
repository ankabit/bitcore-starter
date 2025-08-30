<?php

declare(strict_types=1);

use Modules\Welcome\Actions\WelcomeAction;

return [
    [
        'prefix' => '',
        'routes' => [
            'welcome.index' => [
                'method' => 'GET',
                'path' => '/',
                'action' => WelcomeAction::class,
            ],
        ],
    ],
];
