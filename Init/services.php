<?php

namespace Okay\Modules\Sviat\NovaPoshta;

use Okay\Core\EntityFactory;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Storage\ModuleState;

return [
    ApiKey::class => [
        'class' => ApiKey::class,
        'arguments' => [new SR(Settings::class)],
    ],
    ModuleState::class => [
        'class' => ModuleState::class,
        'arguments' => [new SR(EntityFactory::class)],
    ],
];
