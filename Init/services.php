<?php

namespace Okay\Modules\Sviat\NovaPoshta;

use Okay\Core\Database;
use Okay\Core\Cart;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\FrontTranslations;
use Okay\Core\Languages;
use Okay\Core\Modules\Module;
use Okay\Core\Money;
use Okay\Core\Request;
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
use Okay\Modules\Sviat\NovaPoshta\Cart\CartSelectionValidator;
use Okay\Modules\Sviat\NovaPoshta\Cart\DivisionList;
use Okay\Modules\Sviat\NovaPoshta\Cart\PopularCities;
use Okay\Modules\Sviat\NovaPoshta\Cart\SettlementSearch;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\BoostUpdater;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Cdn\CdnClient;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\CdnDictionarySteps;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryCounts;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryRunner;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DivisionsUpdater;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\SettlementsUpdater;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store\DbDictionaryStore;
use Okay\Modules\Sviat\NovaPoshta\Extenders\FrontExtender;
use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsStore;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyChecker;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryStore;
use Okay\Modules\Sviat\NovaPoshta\Storage\ModuleState;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Backend\SenderDivisions;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;
use Okay\Modules\Sviat\NovaPoshta\Quote\CityRefResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\QuoteService;
use Okay\Modules\Sviat\NovaPoshta\Quote\CartQuotes;
use Okay\Modules\Sviat\NovaPoshta\Quote\DbCityRefStore;
use Okay\Modules\Sviat\NovaPoshta\Extenders\BackendExtender;

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
    ShippingSettings::class => [
        'class' => ShippingSettings::class,
        'arguments' => [new SR(Settings::class), new SR(EntityFactory::class)],
    ],
    BackendExtender::class => [
        'class' => BackendExtender::class,
        'arguments' => [],
    ],
    DbCityRefStore::class => [
        'class' => DbCityRefStore::class,
        'arguments' => [new SR(Database::class), new SR(QueryFactory::class)],
    ],
    CityRefResolver::class => [
        'class' => CityRefResolver::class,
        'arguments' => [new SR(OldApiClient::class), new SR(DbCityRefStore::class)],
    ],
    QuoteService::class => [
        'class' => QuoteService::class,
        'arguments' => [new SR(NewApiClient::class), new SR(OldApiClient::class), new SR(CityRefResolver::class)],
    ],
    CartQuotes::class => [
        'class' => CartQuotes::class,
        'arguments' => [new SR(QuoteService::class), new SR(ShippingSettings::class), new SR(EntityFactory::class), new SR(CodPayments::class), new SR(Module::class), new SR(Money::class), new SR(ModuleState::class)],
    ],
    CodPayments::class => [
        'class' => CodPayments::class,
        'arguments' => [new SR(EntityFactory::class)],
    ],
    SenderDivisions::class => [
        'class' => SenderDivisions::class,
        'arguments' => [new SR(Database::class), new SR(QueryFactory::class)],
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
    PopularCities::class => [
        'class' => PopularCities::class,
        'arguments' => [new SR(ModuleState::class), new SR(EntityFactory::class)],
    ],
    FrontExtender::class => [
        'class' => FrontExtender::class,
        'arguments' => [
            new SR(Design::class),
            new SR(Settings::class),
            new SR(Module::class),
            new SR(Request::class),
            new SR(PopularCities::class),
            new SR(Languages::class),
            new SR(EntityFactory::class),
            new SR(FrontTranslations::class),
            new SR(CartSelectionValidator::class),
            new SR(OrderDeliveryStore::class),
            new SR(Cart::class),
            new SR(SettlementSearch::class),
            new SR(CartQuotes::class),
            new SR(Money::class),
        ],
    ],
    CartSelectionValidator::class => [
        'class' => CartSelectionValidator::class,
        'arguments' => [new SR(EntityFactory::class), new SR(GroupsStore::class), new SR(Module::class), new SR(SettlementSearch::class)],
    ],
    OrderDeliveryStore::class => [
        'class' => OrderDeliveryStore::class,
        'arguments' => [new SR(EntityFactory::class)],
    ],
];
