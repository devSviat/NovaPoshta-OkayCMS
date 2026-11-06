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

            self::assertSame($plain, (new \ReflectionProperty($entityClass, 'fields'))->getValue(), $entityClass);
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
