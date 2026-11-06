<?php

namespace Modules\Sviat\NovaPoshta\Groups;

use Okay\Modules\Sviat\NovaPoshta\Groups\DefaultGroups;
use Okay\Modules\Sviat\NovaPoshta\Groups\DivisionCategory;
use PHPUnit\Framework\TestCase;

class DefaultGroupsTest extends TestCase
{
    public function testBranchAndLockerGroups(): void
    {
        $rows = DefaultGroups::rows();

        self::assertCount(2, $rows);
        self::assertSame(['PostBranch' => true, 'CargoBranch' => true, 'PUDO' => true], $rows[0]['categories']);
        self::assertSame(['Postomat' => true], $rows[1]['categories']);
        self::assertTrue($rows[0]['enabled'] && $rows[1]['enabled']);
        self::assertSame([1, 2], array_column($rows, 'position'));
    }

    public function testEveryCategoryIsInExactlyOneGroup(): void
    {
        $seen = [];
        foreach (DefaultGroups::rows() as $row) {
            foreach (array_keys($row['categories']) as $category) {
                $seen[] = $category;
            }
        }
        sort($seen);
        $all = array_map(static fn(DivisionCategory $c): string => $c->value, DivisionCategory::cases());
        sort($all);

        self::assertSame($all, $seen);
    }

    public function testNamesByLanguage(): void
    {
        [$branch, $locker] = DefaultGroups::rows();

        self::assertSame('Branch', DefaultGroups::name($branch, 'en'));
        self::assertSame('Parcel locker', DefaultGroups::name($locker, 'en'));
        self::assertSame('Відділення', DefaultGroups::name($branch, 'ua'));
        self::assertSame('Поштомат', DefaultGroups::name($locker, 'uk'));
        self::assertSame('Відділення', DefaultGroups::name($branch, 'ru'), 'no Russian content is generated');
    }
}
