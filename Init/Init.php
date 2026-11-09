<?php

namespace Okay\Modules\Sviat\NovaPoshta\Init;

use Okay\Core\Config;
use Okay\Core\Database;
use Okay\Core\EntityFactory;
use Okay\Helpers\DeliveriesHelper;
use Okay\Helpers\NotifyHelper;
use Okay\Helpers\OrdersHelper;
use Okay\Helpers\ValidateHelper;
use Okay\Core\Languages;
use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Core\QueryFactory;
use Okay\Core\Scheduler\Schedule;
use Okay\Core\ServiceLocator;
use Okay\Entities\LanguagesEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Entities\VariantsEntity;
use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Admin\Helpers\BackendDeliveriesHelper;
use Okay\Entities\OrdersEntity;
use Okay\Admin\Requests\BackendProductsRequest;
use Okay\Admin\Requests\BackendPaymentsRequest;
use Okay\Admin\Helpers\BackendPaymentsHelper;
use Okay\Modules\Sviat\NovaPoshta\Extenders\BackendExtender;
use Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryRunner;
use Okay\Modules\Sviat\NovaPoshta\Entities\CityRefEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\GroupEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderRowsCleanup;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillTracking;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyMigration;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrdersEntityFilters;
use Okay\Modules\Sviat\NovaPoshta\Extenders\FrontExtender;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Groups\DefaultGroups;
use Okay\Modules\Sviat\NovaPoshta\Storage\StateEntity;

class Init extends AbstractInit
{
    public function install()
    {
        $this->setBackendMainController('NovaPoshtaSettingsAdmin');
        $this->setModuleType(MODULE_TYPE_DELIVERY);

        $this->createMissingTables(self::tables());
        $this->addDefaultGroups();
        $this->addCoreFields();
    }

    /**
     * Поля, які модуль тримає в таблицях ядра під власними назвами. init() реєструє їх без
     * перевірки: їх створює install(). Пізніша версія, що додає поле, не повинна реєструвати його до свого оновлення —
     * кожен SELECT з тієї таблиці ядра падав би, і вітрина втратила б товари.
     */
    private function addCoreFields(): void
    {
        $this->migrateEntityField(PaymentsEntity::class, new EntityField(CodPayments::FIELD)->setTypeTinyInt(1, false)->setDefault(0));
        $this->migrateEntityField(VariantsEntity::class, new EntityField(VariantVolume::FIELD)->setTypeDecimal('10,5', false)->setDefault(0));
    }

    public function init()
    {
        $this->registerEntityField(PaymentsEntity::class, CodPayments::FIELD);
        $this->registerEntityField(VariantsEntity::class, VariantVolume::FIELD);
        // Поле об'єму варіанта потрапляє в сам рядок через модифікацію product.tpl у module.json.
        $this->registerChainExtension([BackendPaymentsRequest::class, 'postPayment'], [BackendExtender::class, 'paymentPost']);
        $this->registerQueueExtension([BackendPaymentsHelper::class, 'getPaymentMethod'], [BackendExtender::class, 'assignPaymentCod']);
        $this->addBackendBlock('payment_switch_checkboxes', 'payment_cod.tpl');
        $this->registerChainExtension(
            [BackendProductsRequest::class, 'postVariants'],
            [BackendExtender::class, 'cleanVariantVolumes']
        );
        $this->registerQueueExtension(
            [BackendOrdersHelper::class, 'findOrder'],
            [BackendExtender::class, 'assignOrderDelivery']
        );
        $this->registerQueueExtension([BackendOrdersHelper::class, 'delete'], [BackendExtender::class, 'deleteOrders']);
        $this->registerChainExtension([BackendDeliveriesHelper::class, 'getDelivery'], [BackendExtender::class, 'offeredMode']);
        $this->registerQueueExtension([BackendDeliveriesHelper::class, 'getDelivery'], [BackendExtender::class, 'deliveryWarning']);
        $this->addBackendBlock('delivery_price_block', 'delivery_free_warning.tpl');
        $this->registerChainExtension([BackendOrdersHelper::class, 'buildFilter'], [BackendExtender::class, 'listFilter']);
        $this->registerChainExtension([BackendOrdersHelper::class, 'buildCountStatusesFilter'], [BackendExtender::class, 'countFilter']);
        $this->registerQueueExtension([BackendOrdersHelper::class, 'findOrders'], [BackendExtender::class, 'listOrders']);
        $this->registerEntityFilter(OrdersEntity::class, 'np_without_waybill', OrdersEntityFilters::class, 'filter__np_without_waybill');
        $this->registerEntityFilter(OrdersEntity::class, 'np_status_codes', OrdersEntityFilters::class, 'filter__np_status_codes');
        $this->addBackendBlock('orders_list_name', 'orders_status.tpl');
        // Ядро виводить шаблони блоку від останнього зареєстрованого: пункт видачі йде над накладною.
        $this->addBackendBlock('order_custom_block', 'waybill_main.tpl');
        $this->addBackendBlock('order_contact', 'waybill_side.tpl');
        $this->addBackendBlock('order_contact', 'order_delivery.tpl');
        $this->registerBackendController('NovaPoshtaSettingsAdmin');
        $this->addBackendControllerPermission('NovaPoshtaSettingsAdmin', 'delivery');
        $this->registerBackendController('WaybillAdmin');
        $this->addBackendControllerPermission('WaybillAdmin', 'orders');
        $this->registerBackendController('OrderPointAdmin');
        $this->addBackendControllerPermission('OrderPointAdmin', 'orders');

        $this->addFrontBlock('front_cart_delivery', 'cart_delivery.tpl');
        $this->addFrontBlock('front_cart_payment', 'cart_cod_fee.tpl');
        $this->addFrontBlock('front_scripts_after_validate', 'cart_validation.tpl');
        $this->registerQueueExtension(
            [DeliveriesHelper::class, 'getCartDeliveriesList'],
            [FrontExtender::class, 'getCartDeliveriesList']
        );
        $this->registerChainExtension(
            [DeliveriesHelper::class, 'getCartDeliveriesList'],
            [FrontExtender::class, 'applyQuotes']
        );
        $this->registerChainExtension(
            [DeliveriesHelper::class, 'prepareDeliveryPriceInfo'],
            [FrontExtender::class, 'orderPrice']
        );
        $this->registerChainExtension(
            [ValidateHelper::class, 'getCartValidateError'],
            [FrontExtender::class, 'getCartValidateError']
        );
        $this->registerQueueExtension(
            [OrdersHelper::class, 'finalCreateOrderProcedure'],
            [FrontExtender::class, 'saveOrderDelivery']
        );
        foreach ([[OrdersHelper::class, 'getOrderPaymentMethodsList'], [NotifyHelper::class, 'finalEmailOrderUser'], [NotifyHelper::class, 'finalEmailOrderAdmin']] as $hook) {
            $this->registerQueueExtension($hook, [FrontExtender::class, 'assignOrderDelivery']);
        }
        $this->addFrontBlock('front_email_order_user_contact_info', 'email_delivery.tpl');
        $this->addBackendBlock('email_order_admin_contact_info', 'email_delivery.tpl');

        // Кожні 5 хвилин — лише дешева перевірка; runner сам вирішує, чи пора працювати,
        // тож кнопку в адмінці обслуговують за кілька хвилин, а щоденному оновленню не потрібен свій слот.
        $this->registerSchedule(
            new Schedule([DictionaryRunner::class, 'tick'])
                ->name('Nova Poshta: dictionaries')
                ->time('*/5 * * * *')
                ->overlap(false)
                ->timeout(3600)
        );
        $this->registerSchedule(
            new Schedule([LegacyMigration::class, 'tick'])
                ->name('Nova Poshta: data from the old modules')
                ->time('*/5 * * * *')
                ->overlap(false)
                ->timeout(300)
        );
        // Дешева перевірка кожні 15 хвилин; сама робота виконується раз на день (див. OrderRowsCleanup::tick).
        $this->registerSchedule(
            new Schedule([OrderRowsCleanup::class, 'tick'])
                ->name('Nova Poshta: rows of deleted orders')
                ->time('*/15 * * * *')
                ->overlap(false)
                ->timeout(300)
        );
        $this->registerSchedule(
            new Schedule([WaybillTracking::class, 'tick'])
                ->name('Nova Poshta: waybill tracking')
                ->time('*/15 * * * *')
                ->overlap(false)
                ->timeout(900)
        );
    }

    /** @return array<class-string, list<EntityField>> усі таблиці модуля */
    public static function tables(): array
    {
        return [
            StateEntity::class => [
                new EntityField('id')->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
                new EntityField('name')->setTypeVarchar(64, false)->setIndexUnique(),
                new EntityField('value')->setTypeMediumText()->setNullable(),
                new EntityField('updated_at')->setTypeDatetime(false),
            ],
        ] + self::dictionaryTables() + self::orderTables() + self::quoteTables() + self::waybillTables();
    }

    /** @return array<class-string, list<EntityField>> накладна замовлення та її трекінг */
    public static function waybillTables(): array
    {
        return [
            WaybillEntity::class => [
                new EntityField('id')->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
                new EntityField('order_id')->setTypeInt(11, false)->setIndexUnique(),
                new EntityField('number')->setTypeVarchar(14)->setNullable()->setIndexUnique(),
                new EntityField('ref')->setTypeVarchar(36)->setNullable(),
                new EntityField('api')->setTypeVarchar(8)->setNullable(),
                new EntityField('services')->setTypeText()->setNullable(),
                new EntityField('audit_status')->setTypeVarchar(16)->setNullable(),
                new EntityField('audit')->setTypeText()->setNullable(),
                new EntityField('status_code')->setTypeInt(11)->setNullable()->setIndex(),
                new EntityField('status_text')->setTypeVarchar(255)->setNullable(),
                new EntityField('status_at')->setTypeDatetime()->setNullable(),
                new EntityField('scheduled_at')->setTypeDatetime()->setNullable(),
                new EntityField('tracking')->setTypeMediumText()->setNullable(),
                new EntityField('tracked_at')->setTypeDatetime()->setNullable(),
                new EntityField('created_at')->setTypeDatetime(false),
                new EntityField('updated_at')->setTypeDatetime(false),
            ],
        ];
    }

    /** @return array<class-string, list<EntityField>> Ref міст старого API для населених пунктів, для розрахунку ціни */
    public static function quoteTables(): array
    {
        return [
            CityRefEntity::class => [
                new EntityField('settlement_id')->setIndexPrimaryKey()->setTypeInt(11, false),
                new EntityField('city_ref')->setTypeVarchar(36),
                new EntityField('checked_at')->setTypeDatetime(false),
            ],
        ];
    }

    /** @return array<class-string, list<EntityField>> що покупець обрав для замовлення, з копіями назв */
    public static function orderTables(): array
    {
        $name = static fn(string $field): EntityField => new EntityField($field)->setTypeVarchar(255);

        return [
            OrderDeliveryEntity::class => [
                new EntityField('id')->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
                new EntityField('order_id')->setTypeInt(11, false)->setIndexUnique(),
                new EntityField('delivery_id')->setTypeInt(11, false),
                new EntityField('mode')->setTypeVarchar(16, false),
                new EntityField('settlement_id')->setTypeInt(11),
                $name('settlement_name'),
                $name('district_name'),
                $name('area_name'),
                new EntityField('division_id')->setTypeInt(11),
                new EntityField('division_number')->setTypeVarchar(32),
                new EntityField('division_category')->setTypeVarchar(32),
                $name('division_name'),
                $name('division_address'),
                new EntityField('street_name')->setTypeVarchar(100),
                new EntityField('street_ref')->setTypeVarchar(36),
                new EntityField('house')->setTypeVarchar(10),
                new EntityField('flat')->setTypeVarchar(10),
                new EntityField('entrance')->setTypeVarchar(5),
                new EntityField('floor')->setTypeInt(11),
                new EntityField('elevator')->setTypeTinyInt(1, false)->setDefault(0),
                new EntityField('address_note')->setTypeVarchar(40),
                new EntityField('lifting')->setTypeTinyInt(1, false)->setDefault(0),
                new EntityField('delivery_price')->setTypeDecimal('10,2'),
                new EntityField('price_source')->setTypeVarchar(16),
                new EntityField('delivery_term')->setTypeDatetime(),
                new EntityField('cod')->setTypeTinyInt(1, false)->setDefault(0),
                new EntityField('cod_fee')->setTypeDecimal('10,2'),
                // Перенесено зі старих модулів: null, 'migrated' або 'unmatched'.
                new EntityField('legacy')->setTypeVarchar(16),
                new EntityField('created_at')->setTypeDatetime(false),
                new EntityField('updated_at')->setTypeDatetime(false),
            ],
        ];
    }

    /** @return array<class-string, list<EntityField>> довідники Нової Пошти та групи пунктів видачі для кошика */
    public static function dictionaryTables(): array
    {
        $loaded = static fn(): array => [
            new EntityField('row_hash')->setTypeVarchar(40, false),
            new EntityField('seen_run')->setTypeInt(11, false)->setDefault(0),
            new EntityField('updated_at')->setTypeDatetime(false),
        ];

        return [
            SettlementEntity::class => [
                new EntityField('id')->setIndexPrimaryKey()->setTypeInt(11, false),
                new EntityField('name_uk')->setTypeVarchar(255, false),
                new EntityField('name_en')->setTypeVarchar(255),
                new EntityField('district_uk')->setTypeVarchar(255),
                new EntityField('district_en')->setTypeVarchar(255),
                new EntityField('area_uk')->setTypeVarchar(255),
                new EntityField('area_en')->setTypeVarchar(255),
                new EntityField('post_code')->setTypeVarchar(10),
                new EntityField('search_text')->setTypeText(),
                new EntityField('boost')->setTypeInt(11, false)->setDefault(0)->setIndex(),
                new EntityField('prohibited_issuance')->setTypeTinyInt(1, false)->setDefault(0),
                // Обчислюється наперед запуском довідників: пошук міста по ~9.7k таких населених пунктах замість 27.5k.
                new EntityField('has_pickup')->setTypeTinyInt(1, false)->setDefault(0)->setIndex(),
                ...$loaded(),
            ],
            DivisionEntity::class => [
                new EntityField('id')->setIndexPrimaryKey()->setTypeInt(11, false),
                new EntityField('settlement_id')->setTypeInt(11, false)->setIndex(),
                new EntityField('number')->setTypeVarchar(32, false)->setIndex(),
                new EntityField('category')->setTypeVarchar(32, false),
                new EntityField('status')->setTypeVarchar(32, false),
                new EntityField('name_uk')->setTypeVarchar(255, false),
                new EntityField('name_en')->setTypeVarchar(255),
                new EntityField('short_name_uk')->setTypeVarchar(255),
                new EntityField('short_name_en')->setTypeVarchar(255),
                new EntityField('address_uk')->setTypeVarchar(255),
                new EntityField('address_en')->setTypeVarchar(255),
                new EntityField('post_code')->setTypeVarchar(10),
                new EntityField('latitude')->setTypeDecimal('10,7'),
                new EntityField('longitude')->setTypeDecimal('10,7'),
                new EntityField('max_weight')->setTypeDecimal('10,2'),
                new EntityField('max_length')->setTypeInt(11),
                new EntityField('max_width')->setTypeInt(11),
                new EntityField('max_height')->setTypeInt(11),
                new EntityField('max_declared_cost')->setTypeDecimal('14,2'),
                new EntityField('prohibited_issuance')->setTypeTinyInt(1, false)->setDefault(0),
                new EntityField('schedule')->setTypeText()->setNullable(),
                ...$loaded(),
            ],
            GroupEntity::class => [
                new EntityField('id')->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
                new EntityField('position')->setTypeInt(11, false)->setDefault(0),
                new EntityField('enabled')->setTypeTinyInt(1, false)->setDefault(1),
                new EntityField('categories')->setTypeText(),
                new EntityField('name')->setTypeVarchar(255)->setIsLang(),
            ],
        ];
    }

    /**
     * Видалення модуля лишає його таблиці, а migrateEntityTable() — це голий CREATE TABLE:
     * повторне встановлення засмітило б лог повідомленнями "table already exists".
     *
     * @param array<class-string, list<EntityField>> $tables
     */
    private function createMissingTables(array $tables): void
    {
        $serviceLocator = ServiceLocator::getInstance();
        /** @var Database $db */
        $db = $serviceLocator->getService(Database::class);
        $db->query($serviceLocator->getService(QueryFactory::class)->newSqlQuery()->setStatement('SHOW TABLES'));
        $existing = [];
        foreach ($db->results() as $row) {
            $existing[] = (string)current((array)$row);
        }

        $prefix = (string)$serviceLocator->getService(Config::class)->get('db_prefix');
        foreach ($tables as $entityClass => $fields) {
            // Ядро підставляє префікс таблиць замість початкового "__".
            if (!in_array($prefix . substr($entityClass::getTable(), 2), $existing, true)) {
                $this->migrateEntityTable($entityClass, $fields);
            }
        }
    }

    /** Кожна мова сайту отримує свою назву: entity записує мовний рядок для поточної мови. */
    private function addDefaultGroups(): void
    {
        $serviceLocator = ServiceLocator::getInstance();
        /** @var EntityFactory $entityFactory */
        $entityFactory = $serviceLocator->getService(EntityFactory::class);
        /** @var Languages $languages */
        $languages = $serviceLocator->getService(Languages::class);
        $groups = $entityFactory->get(GroupEntity::class);
        // Видалення модуля лишає його таблиці: повторне встановлення не повинне додати типові значення вдруге.
        if ($groups->count() > 0) {
            return;
        }
        $siteLanguages = $entityFactory->get(LanguagesEntity::class)->find();
        $initialLanguage = $languages->getLangId();

        foreach (DefaultGroups::rows() as $row) {
            $id = $groups->add([
                'position' => $row['position'],
                'enabled' => (int)$row['enabled'],
                'categories' => json_encode($row['categories']),
                'name' => DefaultGroups::name($row, ''),
            ]);
            foreach ($siteLanguages as $language) {
                $languages->setLangId((int)$language->id);
                $groups->update($id, ['name' => DefaultGroups::name($row, (string)$language->label)]);
            }
        }
        $languages->setLangId($initialLanguage);
    }
}
