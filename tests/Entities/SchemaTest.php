<?php

namespace Modules\Sviat\NovaPoshta\Entities;

use Okay\Core\Modules\EntityField;
use Okay\Modules\Sviat\NovaPoshta\Init\Init;
use PHPUnit\Framework\TestCase;

/** The migration and the entity list the same columns: a column missing on either side is lost silently. */
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
            // Registered by Init::init() once the 0.4.0 / 0.5.0 updates have created them (upgrade window).
            if ($entityClass === \Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity::class) {
                $late = array_map(static fn(EntityField $f): string => $f->getName(), [...Init::pricingFields(), ...Init::addressFields(), Init::codFeeField()]);
                self::assertSame([], array_values(array_intersect($late, $entityFields)), 'registered late, not static');
                $plain = array_values(array_diff($plain, $late));
            }
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
}
