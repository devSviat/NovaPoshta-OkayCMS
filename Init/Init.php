<?php

namespace Okay\Modules\Sviat\NovaPoshta\Init;

use Okay\Core\EntityFactory;
use Okay\Core\Languages;
use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Core\ServiceLocator;
use Okay\Entities\LanguagesEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\GroupEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Groups\DefaultGroups;
use Okay\Modules\Sviat\NovaPoshta\Storage\StateEntity;

class Init extends AbstractInit
{
    public function install()
    {
        // Not a delivery module yet (MODULE_TYPE_DELIVERY comes with the cart):
        // otherwise a delivery method could be bound to a module that cannot deliver.
        $this->setBackendMainController('NovaPoshtaSettingsAdmin');

        foreach (self::tables() as $entityClass => $fields) {
            $this->migrateEntityTable($entityClass, $fields);
        }
        $this->addDefaultGroups();
    }

    /**
     * Only an upgrade from 0.1.0 gets here: on a clean install the core runs update_*
     * for versions above 1.0.0 only, and install() has already created everything.
     */
    public function update_0_2_0()
    {
        foreach (self::dictionaryTables() as $entityClass => $fields) {
            $this->migrateEntityTable($entityClass, $fields);
        }
        $this->addDefaultGroups();
    }

    public function init()
    {
        $this->registerBackendController('NovaPoshtaSettingsAdmin');
        $this->addBackendControllerPermission('NovaPoshtaSettingsAdmin', 'delivery');
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
        ] + self::dictionaryTables();
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

    /** Each site language gets its own name: the entity writes a language row per current language. */
    private function addDefaultGroups(): void
    {
        $serviceLocator = ServiceLocator::getInstance();
        /** @var EntityFactory $entityFactory */
        $entityFactory = $serviceLocator->getService(EntityFactory::class);
        /** @var Languages $languages */
        $languages = $serviceLocator->getService(Languages::class);
        $groups = $entityFactory->get(GroupEntity::class);
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
