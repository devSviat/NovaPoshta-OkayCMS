<?php

namespace Okay\Modules\Sviat\NovaPoshta;

use Okay\Core\Database;
use Okay\Core\EntityFactory;
use Okay\Core\QueryFactory;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\CurlDownloader;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\CurlTransport;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Backend\KeySettings;
use Okay\Modules\Sviat\NovaPoshta\Cart\DivisionList;
use Okay\Modules\Sviat\NovaPoshta\Cart\SettlementSearch;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\BoostUpdater;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Cdn\CdnClient;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\CdnDictionarySteps;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryCounts;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryRunner;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DivisionsUpdater;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\SettlementsUpdater;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store\DbDictionaryStore;
use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsStore;
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
    KeySettings::class => [
        'class' => KeySettings::class,
        'arguments' => [new SR(Settings::class), new SR(ApiKey::class), new SR(KeyChecker::class)],
    ],
    CurlDownloader::class => [
        'class' => CurlDownloader::class,
        'arguments' => [],
    ],
    CdnClient::class => [
        'class' => CdnClient::class,
        'arguments' => [new SR(CurlTransport::class), new SR(CurlDownloader::class)],
    ],
    DbDictionaryStore::class => [
        'class' => DbDictionaryStore::class,
        'arguments' => [new SR(Database::class), new SR(QueryFactory::class)],
    ],
    DivisionsUpdater::class => [
        'class' => DivisionsUpdater::class,
        'arguments' => [new SR(DbDictionaryStore::class)],
    ],
    SettlementsUpdater::class => [
        'class' => SettlementsUpdater::class,
        'arguments' => [new SR(DbDictionaryStore::class)],
    ],
    BoostUpdater::class => [
        'class' => BoostUpdater::class,
        'arguments' => [new SR(NewApiClient::class), new SR(DbDictionaryStore::class)],
    ],
    CdnDictionarySteps::class => [
        'class' => CdnDictionarySteps::class,
        'arguments' => [
            new SR(CdnClient::class),
            new SR(DivisionsUpdater::class),
            new SR(SettlementsUpdater::class),
            new SR(BoostUpdater::class),
            new SR(EntityFactory::class),
        ],
    ],
    DictionaryRunner::class => [
        'class' => DictionaryRunner::class,
        'arguments' => [new SR(CdnDictionarySteps::class), new SR(ModuleState::class)],
    ],
    DictionaryCounts::class => [
        'class' => DictionaryCounts::class,
        'arguments' => [new SR(EntityFactory::class)],
    ],
    GroupsStore::class => [
        'class' => GroupsStore::class,
        'arguments' => [new SR(EntityFactory::class)],
    ],
    SettlementSearch::class => [
        'class' => SettlementSearch::class,
        'arguments' => [new SR(Database::class), new SR(QueryFactory::class)],
    ],
    DivisionList::class => [
        'class' => DivisionList::class,
        'arguments' => [new SR(Database::class), new SR(QueryFactory::class), new SR(GroupsStore::class)],
    ],
];
