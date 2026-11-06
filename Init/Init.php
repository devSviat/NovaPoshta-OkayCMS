<?php

namespace Okay\Modules\Sviat\NovaPoshta\Init;

use Okay\Core\Config;
use Okay\Core\Database;
use Okay\Core\EntityFactory;
use Okay\Helpers\DeliveriesHelper;
use Okay\Core\Languages;
use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Core\QueryFactory;
use Okay\Core\Scheduler\Schedule;
use Okay\Core\ServiceLocator;
use Okay\Entities\LanguagesEntity;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryRunner;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\GroupEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity;
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
    }

    /**
     * Only an upgrade from 0.1.0 gets here: on a clean install the core runs update_*
     * for versions above 1.0.0 only, and install() has already created everything.
     */
    public function update_0_2_0()
    {
        $this->createMissingTables(self::dictionaryTables());
        $this->addDefaultGroups();
    }

    /** The cart picks pickup points from 0.3.0 on: the module becomes a delivery module. */
    public function update_0_3_0()
    {
        $this->setModuleType(MODULE_TYPE_DELIVERY);
        $this->createMissingTables(self::orderTables());
        $this->migrateEntityField(SettlementEntity::class, self::hasPickupField());
    }

    public function init()
    {
        $this->registerBackendController('NovaPoshtaSettingsAdmin');
        $this->addBackendControllerPermission('NovaPoshtaSettingsAdmin', 'delivery');

        $this->addFrontBlock('front_cart_delivery', 'cart_delivery.tpl');
        $this->registerQueueExtension(
            [DeliveriesHelper::class, 'getCartDeliveriesList'],
            [FrontExtender::class, 'getCartDeliveriesList']
        );

        // Every 5 minutes is only a cheap check; the runner itself decides whether work is due,
        // so the admin button is served within minutes and the daily update needs no own slot.
        $this->registerSchedule(
            new Schedule([DictionaryRunner::class, 'tick'])
                ->name('Nova Poshta: dictionaries')
                ->time('*/5 * * * *')
                ->overlap(false)
                ->timeout(3600)
        );
    }

    /** @return array<class-string, list<EntityField>> every table of the module */
    public static function tables(): array
    {
        return [
            StateEntity::class => [
                new EntityField('id')->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
                new EntityField('name')->setTypeVarchar(64, false)->setIndexUnique(),
                new EntityField('value')->setTypeMediumText()->setNullable(),
                new EntityField('updated_at')->setTypeDatetime(false),
            ],
        ] + self::dictionaryTables() + self::orderTables();
    }

    /** Precomputed by the dictionary run: city search over ~9.7k such settlements instead of 27.5k. */
    private static function hasPickupField(): EntityField
    {
        return new EntityField('has_pickup')->setTypeTinyInt(1, false)->setDefault(0)->setIndex();
    }

    /** @return array<class-string, list<EntityField>> tables added in 0.3.0 */
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
                new EntityField('created_at')->setTypeDatetime(false),
                new EntityField('updated_at')->setTypeDatetime(false),
            ],
        ];
    }

    /** @return array<class-string, list<EntityField>> tables added in 0.2.0 */
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
                self::hasPickupField(),
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
     * Deleting a module keeps its tables, and migrateEntityTable() is a bare CREATE TABLE:
     * a reinstall would fill the log with "table already exists".
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
            // The core writes the table prefix in place of the leading "__".
            if (!in_array($prefix . substr($entityClass::getTable(), 2), $existing, true)) {
                $this->migrateEntityTable($entityClass, $fields);
            }
        }
    }

    /** Each site language gets its own name: the entity writes a language row per current language. */
    private function addDefaultGroups(): void
    {
        $serviceLocator = ServiceLocator::getInstance();
        /** @var EntityFactory $entityFactory */
        $entityFactory = $serviceLocator->getService(EntityFactory::class);
        /** @var Languages $languages */
        $languages = $serviceLocator->getService(Languages::class);
        $groups = $entityFactory->get(GroupEntity::class);
        // Deleting a module keeps its tables: a reinstall must not add the defaults twice.
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
