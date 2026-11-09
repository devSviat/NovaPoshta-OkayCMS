<?php

namespace Modules\Sviat\NovaPoshta\Entities;

use Okay\Core\Modules\EntityField;
use Okay\Modules\Sviat\NovaPoshta\Init\Init;
use PHPUnit\Framework\TestCase;

/** Міграція й сутність перелічують ті самі колонки: колонка, якої бракує з будь-якого боку, тихо губиться. */
class SchemaTest extends TestCase
{
    public function testEveryEntityMatchesItsMigration(): void
    {
        self::assertNotEmpty(Init::tables());

        foreach (Init::tables() as $entityClass => $fields) {
            $plain = [];
            $lang = [];
            foreach ($fields as $field) {
                self::assertInstanceOf(EntityField::class, $field);
                if ($field->isLangField()) {
                    $lang[] = $field->getName();
                } else {
                    $plain[] = $field->getName();
                }
            }

            $entityFields = (new \ReflectionProperty($entityClass, 'fields'))->getValue();
            self::assertSame($plain, $entityFields, $entityClass);
            $entityLang = (new \ReflectionProperty($entityClass, 'langFields'))->getValue() ?? [];
            self::assertSame($lang, $entityLang, $entityClass);
        }
    }

    public function testStageTwoTablesAreASubsetOfAllTables(): void
    {
        foreach (array_keys(Init::dictionaryTables()) as $entityClass) {
            self::assertArrayHasKey($entityClass, Init::tables());
        }
        self::assertCount(3, Init::dictionaryTables());
    }

    public function testOrderDeliveryTableIsInstalled(): void
    {
        self::assertArrayHasKey(\Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity::class, Init::tables());
        self::assertArrayHasKey(\Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity::class, Init::orderTables());
    }

    public function testWaybillTableIsInstalled(): void
    {
        $tables = Init::waybillTables();
        self::assertArrayHasKey(\Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity::class, Init::tables());
        $fields = $tables[\Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity::class];
        self::assertSame(
            ['id', 'order_id', 'number', 'ref', 'api', 'services', 'audit_status', 'audit', 'status_code', 'status_text', 'status_at', 'scheduled_at', 'tracking', 'tracked_at', 'created_at', 'updated_at'],
            array_map(static fn(EntityField $f): string => $f->getName(), $fields),
        );
        self::assertArrayHasKey(EntityField::INDEX_UNIQUE, $fields[1]->getIndexes());
        // Одна накладна належить одному замовленню; поки її створюють, номера немає (NULL вільно повторюється).
        self::assertArrayHasKey(EntityField::INDEX_UNIQUE, $fields[2]->getIndexes(), 'number');
        self::assertTrue($fields[2]->isNullable(), 'number');
        self::assertTrue($fields[4]->isNullable(), 'api: how it was made, unknown while it is being made');
    }

    public function testDictionaryIdsAreNovaPoshtaIdsNotAutoIncrement(): void
    {
        foreach (Init::dictionaryTables() as $entityClass => $fields) {
            if (str_ends_with($entityClass, 'GroupEntity')) {
                continue;
            }
            self::assertTrue($fields[0]->isPrimaryKey(), $entityClass);
            self::assertFalse($fields[0]->isAutoIncrement(), $entityClass);
        }
    }

    public function testNoPersonalDataColumns(): void
    {
        foreach (Init::tables() as $fields) {
            foreach ($fields as $field) {
                self::assertDoesNotMatchRegularExpression('/responsible|phone|person|photo/i', $field->getName());
            }
        }
    }

    /** 1.0.0 — перший реліз: install() створює всю схему, оновлюватися нема з чого. */
    public function testNoUpgradeSteps(): void
    {
        $updates = array_filter(get_class_methods(Init::class), static fn(string $m): bool => str_starts_with($m, 'update_'));

        self::assertSame([], array_values($updates));
    }

    /** Дати зберігаються в базі як дати, а не як рядки чи секунди Unix (EntityField ядра не має DATE). */
    public function testDatesAreDatetimeColumns(): void
    {
        $type = static function (string $entity, string $name): string {
            foreach (Init::tables()[$entity] as $field) {
                if ($field->getName() === $name) {
                    return strtolower((string)$field->getType());
                }
            }

            return '';
        };

        self::assertSame('datetime', $type(\Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity::class, 'delivery_term'));
        self::assertSame('datetime', $type(\Okay\Modules\Sviat\NovaPoshta\Entities\CityRefEntity::class, 'checked_at'));
    }
}
