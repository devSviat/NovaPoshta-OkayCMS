<?php

namespace Okay\Modules\Sviat\NovaPoshta;

use Okay\Core\EntityFactory;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\CurlTransport;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyChecker;
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
    CurlTransport::class => [
        'class' => CurlTransport::class,
        'arguments' => [],
    ],
    JwtProvider::class => [
        'class' => JwtProvider::class,
        'arguments' => [new SR(CurlTransport::class), new SR(ModuleState::class), new SR(ApiKey::class)],
    ],
    NewApiClient::class => [
        'class' => NewApiClient::class,
        'arguments' => [new SR(CurlTransport::class), new SR(JwtProvider::class)],
    ],
    OldApiClient::class => [
        'class' => OldApiClient::class,
        'arguments' => [new SR(CurlTransport::class), new SR(ApiKey::class)],
    ],
    KeyChecker::class => [
        'class' => KeyChecker::class,
        'arguments' => [new SR(CurlTransport::class), new SR(OldApiClient::class), new SR(JwtProvider::class), new SR(ModuleState::class)],
    ],
];
