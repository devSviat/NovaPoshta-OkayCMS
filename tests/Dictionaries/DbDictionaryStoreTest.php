<?php

namespace Modules\Sviat\NovaPoshta\Dictionaries;

use Aura\Sql\ExtendedPdo;
use Aura\SqlQuery\QueryFactory as AuraQueryFactory;
use Aura\SqlQuery\QueryInterface;
use Okay\Core\Database;
use Okay\Core\QueryFactory;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store\DbDictionaryStore;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store\DictionaryStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Database::query() OkayCMS 4.5.3 бере прив'язки раніше за текст запиту, а Aura Insert::addRows()
 * добудовує останній рядок пакета лише під час збирання тексту: пакет рівно з двох рядків губив значення
 * другого, і дельта довідника відділень падала щогодини.
 */
class DbDictionaryStoreTest extends TestCase
{
    /** @return array<string, array{int}> */
    public static function rowCounts(): array
    {
        return ['один рядок' => [1], 'два рядки' => [2], 'три рядки' => [3]];
    }

    #[DataProvider('rowCounts')]
    public function testUpsertBindsEveryPlaceholderWhenBindsAreReadFirst(int $count): void
    {
        $seen = [];
        $db = $this->createStub(Database::class);
        $db->method('query')->willReturnCallback(static function (QueryInterface $query) use (&$seen): bool {
            // Порядок стоку 4.5.3: спершу прив'язки, потім текст.
            $bind = $query->getBindValues();
            $seen[] = [$query->getStatement(), $bind];

            return true;
        });
        // Деструктор Database від'єднує pdo, а заглушка конструктора не викликає.
        new \ReflectionProperty(Database::class, 'pdo')->setValue($db, $this->createStub(ExtendedPdo::class));
        // Обгортки запитів QueryFactory ядра в конструкторі беруть Database з ServiceLocator, а в CI бази немає.
        $queryFactory = $this->createStub(QueryFactory::class);
        $queryFactory->method('newInsert')->willReturnCallback(static fn() => new AuraQueryFactory('mysql')->newInsert());
        $store = new DbDictionaryStore($db, $queryFactory);

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = ['id' => 100 + $i, 'name' => "Відділення {$i}", 'row_hash' => "h{$i}"];
        }
        $store->upsert(DictionaryStore::DIVISIONS, $rows, 7);

        $this->assertCount(1, $seen);
        [$statement, $bind] = $seen[0];
        preg_match_all('/:([a-z_][a-z0-9_]*)/i', $statement, $placeholders);
        $this->assertNotEmpty($placeholders[1]);
        foreach ($placeholders[1] as $name) {
            $this->assertArrayHasKey($name, $bind, ":{$name} без значення");
        }
    }
}
