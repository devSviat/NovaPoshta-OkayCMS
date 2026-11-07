<?php

namespace Okay\Modules\Sviat\NovaPoshta;

use Okay\Core\Config;
use Okay\Core\Database;
use Okay\Core\Cart;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\FrontTranslations;
use Okay\Core\Languages;
use Okay\Core\Modules\Module;
use Okay\Core\Money;
use Okay\Core\BackendTranslations;
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
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderParcel;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderPointChange;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStore;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillService;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillTracking;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillView;
use Okay\Modules\Sviat\NovaPoshta\Waybills\SenderParties;
use Okay\Modules\Sviat\NovaPoshta\Waybills\SettlementCityRefs;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillDrafts;
use Okay\Modules\Sviat\NovaPoshta\Waybills\InvoicePaymentData;
use Okay\Modules\Sviat\NovaPoshta\Waybills\MassOrderSource;
use Okay\Modules\Sviat\NovaPoshta\Waybills\MassWaybills;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrdersMassAction;
use Okay\Modules\Sviat\NovaPoshta\Waybills\AmountDue;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillSettings;
use Okay\Modules\Sviat\NovaPoshta\Waybills\SenderContacts;
use Okay\Modules\Sviat\NovaPoshta\Storage\ModuleState;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Backend\SenderDivisions;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyAddressMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyCityMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyDivisionMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyPlaces;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyPlacesDb;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyOrders;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyOrdersDb;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyTables;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyData;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyMigration;
use Okay\Modules\Sviat\NovaPoshta\Legacy\Steps\OrdersStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\Steps\PaymentsStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\Steps\PopularStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\Steps\SettingsStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\Steps\VolumesStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\Steps\WaybillsStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyTargets;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyTargetsDb;
use Okay\Modules\Sviat\NovaPoshta\Quote\CityRefResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\AddressCityResolver;
use Okay\Modules\Sviat\NovaPoshta\Cart\StreetSearch;
use Okay\Modules\Sviat\NovaPoshta\Quote\QuoteService;
use Okay\Modules\Sviat\NovaPoshta\Quote\CartQuotes;
use Okay\Modules\Sviat\NovaPoshta\Quote\DbCityRefStore;
use Okay\Modules\Sviat\NovaPoshta\Extenders\BackendExtender;
use Okay\Modules\Sviat\NovaPoshta\Cart\UahTotal;

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
    UahTotal::class => [
        'class' => UahTotal::class,
        'arguments' => [new SR(EntityFactory::class), new SR(Money::class)],
    ],
    BackendExtender::class => [
        'class' => BackendExtender::class,
        'arguments' => [new SR(Design::class), new SR(OrderDeliveryStore::class), new SR(CodPayments::class), new SR(EntityFactory::class), new SR(WaybillView::class), new SR(Module::class), new SR(ShippingSettings::class), new SR(WaybillStore::class), new SR(BackendTranslations::class), new SR(Request::class), new SR(OrdersMassAction::class)],
    ],
    DbCityRefStore::class => [
        'class' => DbCityRefStore::class,
        'arguments' => [new SR(Database::class), new SR(QueryFactory::class)],
    ],
    CityRefResolver::class => [
        'class' => CityRefResolver::class,
        'arguments' => [new SR(OldApiClient::class), new SR(DbCityRefStore::class)],
    ],
    AddressCityResolver::class => [
        'class' => AddressCityResolver::class,
        'arguments' => [new SR(OldApiClient::class), new SR(DbCityRefStore::class), new SR(CityRefResolver::class)],
    ],
    StreetSearch::class => [
        'class' => StreetSearch::class,
        'arguments' => [new SR(OldApiClient::class), new SR(AddressCityResolver::class)],
    ],
    QuoteService::class => [
        'class' => QuoteService::class,
        'arguments' => [new SR(NewApiClient::class), new SR(OldApiClient::class), new SR(CityRefResolver::class)],
    ],
    CartQuotes::class => [
        'class' => CartQuotes::class,
        'arguments' => [new SR(QuoteService::class), new SR(ShippingSettings::class), new SR(EntityFactory::class), new SR(CodPayments::class), new SR(Module::class), new SR(Money::class), new SR(ModuleState::class), new SR(AddressCityResolver::class)],
    ],
    CodPayments::class => [
        'class' => CodPayments::class,
        'arguments' => [new SR(EntityFactory::class), new SR(Settings::class)],
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
            new SR(UahTotal::class),
            new SR(WaybillStore::class),
        ],
    ],
    CartSelectionValidator::class => [
        'class' => CartSelectionValidator::class,
        'arguments' => [new SR(EntityFactory::class), new SR(GroupsStore::class), new SR(Module::class), new SR(SettlementSearch::class), new SR(ShippingSettings::class)],
    ],
    WaybillSettings::class => [
        'class' => WaybillSettings::class,
        'arguments' => [new SR(Settings::class)],
    ],
    SenderContacts::class => [
        'class' => SenderContacts::class,
        'arguments' => [new SR(OldApiClient::class), new SR(ModuleState::class)],
    ],
    OrderPointChange::class => [
        'class' => OrderPointChange::class,
        'arguments' => [new SR(EntityFactory::class), new SR(Module::class), new SR(GroupsStore::class), new SR(OrderDeliveryStore::class), new SR(OrderParcel::class), new SR(CartQuotes::class), new SR(ShippingSettings::class), new SR(WaybillStore::class)],
    ],
    OrderParcel::class => [
        'class' => OrderParcel::class,
        'arguments' => [new SR(EntityFactory::class), new SR(Money::class)],
    ],
    AmountDue::class => [
        'class' => AmountDue::class,
    ],
    WaybillDrafts::class => [
        'class' => WaybillDrafts::class,
        'arguments' => [new SR(EntityFactory::class), new SR(OrderDeliveryStore::class), new SR(WaybillSettings::class), new SR(ShippingSettings::class), new SR(CodPayments::class), new SR(OrderParcel::class), new SR(AmountDue::class), new SR(InvoicePaymentData::class)],
    ],
    MassOrderSource::class => [
        'class' => MassOrderSource::class,
        'arguments' => [new SR(EntityFactory::class), new SR(Module::class), new SR(WaybillDrafts::class)],
    ],
    OrdersMassAction::class => [
        'class' => OrdersMassAction::class,
        'arguments' => [new SR(MassWaybills::class)],
    ],
    MassWaybills::class => [
        'class' => MassWaybills::class,
        'arguments' => [new SR(WaybillService::class), new SR(WaybillStore::class), new SR(MassOrderSource::class)],
    ],
    InvoicePaymentData::class => [
        'class' => InvoicePaymentData::class,
        'arguments' => [new SR(EntityFactory::class)],
    ],
    SenderParties::class => [
        'class' => SenderParties::class,
        'arguments' => [new SR(SenderContacts::class), new SR(WaybillSettings::class), new SR(ShippingSettings::class), new SR(EntityFactory::class)],
    ],
    SettlementCityRefs::class => [
        'class' => SettlementCityRefs::class,
        'arguments' => [new SR(EntityFactory::class), new SR(AddressCityResolver::class)],
    ],
    WaybillView::class => [
        'class' => WaybillView::class,
        'arguments' => [new SR(EntityFactory::class), new SR(OrderDeliveryStore::class), new SR(WaybillStore::class), new SR(WaybillDrafts::class), new SR(CodPayments::class)],
    ],
    WaybillTracking::class => [
        'class' => WaybillTracking::class,
        'arguments' => [new SR(NewApiClient::class), new SR(OldApiClient::class), new SR(WaybillStore::class), new SR(ModuleState::class)],
    ],
    WaybillService::class => [
        'class' => WaybillService::class,
        'arguments' => [new SR(OldApiClient::class), new SR(NewApiClient::class), new SR(WaybillStore::class), new SR(SenderParties::class), new SR(SettlementCityRefs::class)],
    ],
    WaybillStore::class => [
        'class' => WaybillStore::class,
        'arguments' => [new SR(EntityFactory::class)],
    ],
    OrderDeliveryStore::class => [
        'class' => OrderDeliveryStore::class,
        'arguments' => [new SR(EntityFactory::class)],
    ],
    LegacyPlaces::class => [
        'class' => LegacyPlacesDb::class,
        'arguments' => [new SR(Database::class), new SR(QueryFactory::class), new SR(DbCityRefStore::class), new SR(OldApiClient::class)],
    ],
    LegacyCityMatcher::class => [
        'class' => LegacyCityMatcher::class,
        'arguments' => [new SR(LegacyPlaces::class)],
    ],
    LegacyDivisionMatcher::class => [
        'class' => LegacyDivisionMatcher::class,
        'arguments' => [new SR(LegacyPlaces::class)],
    ],
    LegacyAddressMatcher::class => [
        'class' => LegacyAddressMatcher::class,
        'arguments' => [new SR(LegacyPlaces::class)],
    ],
    LegacyOrders::class => [
        'class' => LegacyOrdersDb::class,
        'arguments' => [new SR(EntityFactory::class), new SR(OrderDeliveryStore::class), new SR(WaybillStore::class)],
    ],
    LegacyTargets::class => [
        'class' => LegacyTargetsDb::class,
        'arguments' => [new SR(EntityFactory::class), new SR(Settings::class), new SR(PopularCities::class)],
    ],
    LegacyData::class => [
        'class' => LegacyTables::class,
        'arguments' => [new SR(Database::class), new SR(QueryFactory::class), new SR(Config::class), new SR(Settings::class), new SR(EntityFactory::class)],
    ],
    OrdersStep::class => [
        'class' => OrdersStep::class,
        'arguments' => [new SR(LegacyData::class), new SR(LegacyOrders::class), new SR(LegacyPlaces::class), new SR(LegacyCityMatcher::class), new SR(LegacyDivisionMatcher::class), new SR(LegacyAddressMatcher::class)],
    ],
    WaybillsStep::class => [
        'class' => WaybillsStep::class,
        'arguments' => [new SR(LegacyData::class), new SR(LegacyOrders::class)],
    ],
    PaymentsStep::class => [
        'class' => PaymentsStep::class,
        'arguments' => [new SR(LegacyData::class), new SR(LegacyTargets::class)],
    ],
    VolumesStep::class => [
        'class' => VolumesStep::class,
        'arguments' => [new SR(LegacyData::class), new SR(LegacyTargets::class)],
    ],
    PopularStep::class => [
        'class' => PopularStep::class,
        'arguments' => [new SR(LegacyData::class), new SR(LegacyTargets::class), new SR(LegacyCityMatcher::class)],
    ],
    SettingsStep::class => [
        'class' => SettingsStep::class,
        'arguments' => [new SR(LegacyData::class), new SR(LegacyTargets::class), new SR(LegacyCityMatcher::class), new SR(LegacyDivisionMatcher::class)],
    ],
    LegacyMigration::class => [
        'class' => LegacyMigration::class,
        // Orders before waybills: a waybill's card reads the order's point.
        'arguments' => [new SR(ModuleState::class), new SR(OrdersStep::class), new SR(WaybillsStep::class), new SR(PaymentsStep::class), new SR(VolumesStep::class), new SR(PopularStep::class), new SR(SettingsStep::class)],
    ],
];
