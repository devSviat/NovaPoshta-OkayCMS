<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Cart\DivisionList;
use PHPUnit\Framework\TestCase;

/** The pure part: groups with counts, the chosen group's page of points, fit problems. */
class DivisionListTest extends TestCase
{
    private static function groupRows(): array
    {
        return [
            (object)['id' => 1, 'name' => 'Відділення', 'enabled' => 1, 'position' => 1, 'categories' => '{"PostBranch":true,"CargoBranch":true,"PUDO":false}'],
            (object)['id' => 2, 'name' => 'Поштомат', 'enabled' => 1, 'position' => 2, 'categories' => '{"Postomat":true}'],
            (object)['id' => 3, 'name' => 'Вимкнена', 'enabled' => 0, 'position' => 3, 'categories' => '{"CargoBranch":true}'],
        ];
    }

    private static function point(int $id, string $number, string $category, array $override = []): object
    {
        return (object)($override + [
            'id' => $id, 'number' => $number, 'category' => $category,
            'name_uk' => "Відділення №{$number} (до 30 кг): вул. Тестова, {$id}", 'name_en' => "Branch No {$number} (up to 30 kg): Testova St, {$id}",
            'address_uk' => "Київ, Тестова, {$id}", 'address_en' => "Kyiv, Testova, {$id}",
            'max_weight' => '30.00', 'max_declared_cost' => '100000.00',
            'schedule' => json_encode([['day' => 'friday', 'from' => '08:00', 'to' => '20:00']]),
        ]);
    }

    public function testGroupsCountOnlyTheirShownCategories(): void
    {
        $groups = DivisionList::groupCounts(self::groupRows(), ['PostBranch' => 3, 'CargoBranch' => 2, 'PUDO' => 40, 'Postomat' => 7]);

        self::assertSame([['id' => 1, 'name' => 'Відділення', 'count' => 5], ['id' => 2, 'name' => 'Поштомат', 'count' => 7]], $groups);
    }

    public function testGroupWithoutPointsIsLeftOut(): void
    {
        self::assertSame([1], array_column(DivisionList::groupCounts(self::groupRows(), ['PostBranch' => 1]), 'id'));
    }

    public function testShownCategoriesOfAGroup(): void
    {
        self::assertSame(['PostBranch', 'CargoBranch'], DivisionList::categoriesOf(self::groupRows(), 1));
        self::assertSame([], DivisionList::categoriesOf(self::groupRows(), 3), 'disabled group');
        self::assertSame([], DivisionList::categoriesOf(self::groupRows(), 99));
    }

    public function testUnfitPointsGoLastWithTheirReasons(): void
    {
        $page = DivisionList::page(
            [self::point(1, '1', 'PostBranch', ['max_weight' => '10.00']), self::point(2, '2', 'PostBranch')],
            new CartMetrics(12.4, false, 100), 'ua', 'friday',
        );

        self::assertSame([2, 1], array_column($page, 'id'));
        self::assertSame(['too_heavy'], $page[1]['problems']);
        self::assertArrayNotHasKey('problems', $page[0], 'no problems → no field');
    }

    public function testNovaPoshtaNameIsSplitIntoTitleAndPlace(): void
    {
        $point = DivisionList::page([self::point(1, '11/117', 'PostBranch')], new CartMetrics(1, false, 1), 'ua', 'friday')[0];

        self::assertSame('Відділення №11/117 (до 30 кг)', $point['title']);
        self::assertSame('вул. Тестова, 1', $point['place']);
    }

    public function testNameWithoutColonFallsBackToTheAddress(): void
    {
        $point = DivisionList::page([self::point(1, '5', 'Postomat', ['name_uk' => 'Поштомат №5'])], new CartMetrics(1, false, 1), 'ua', 'friday')[0];

        self::assertSame('Поштомат №5', $point['title']);
        self::assertSame('Київ, Тестова, 1', $point['place']);
    }

    public function testEnglishNamesForEnglishPages(): void
    {
        self::assertSame('Testova St, 1', DivisionList::page([self::point(1, '1', 'PostBranch')], new CartMetrics(1, false, 1), 'en', 'friday')[0]['place']);
    }

    public function testTodaysClosingTime(): void
    {
        $metrics = new CartMetrics(1, false, 1);
        self::assertSame('20:00', DivisionList::page([self::point(1, '1', 'PostBranch')], $metrics, 'ua', 'friday')[0]['closesToday']);

        $allDay = self::point(2, '2', 'Postomat', ['schedule' => json_encode([['day' => 'friday', 'from' => '00:00', 'to' => '23:59']])]);
        self::assertSame('24/7', DivisionList::page([$allDay], $metrics, 'ua', 'friday')[0]['closesToday']);

        $closed = self::point(3, '3', 'PostBranch', ['schedule' => json_encode([['day' => 'friday', 'from' => '-', 'to' => '-']])]);
        self::assertArrayNotHasKey('closesToday', DivisionList::page([$closed], $metrics, 'ua', 'friday')[0]);
    }

    public function testAFullPageStaysSmall(): void
    {
        $points = [];
        for ($i = 1; $i <= DivisionList::PAGE; $i++) {
            $points[] = self::point($i, (string)$i, 'Postomat', ['name_uk' => "Поштомат \"Нова Пошта\" №{$i}: вул. Довга-Предовга Назва Вулиці, {$i}, ТРЦ «Великий» (біля входу)"]);
        }

        self::assertLessThan(30 * 1024, strlen(json_encode(DivisionList::page($points, new CartMetrics(1, false, 1), 'ua', 'friday'), JSON_UNESCAPED_UNICODE)));
    }

    public function testSearchTextIsMadeSafeForLike(): void
    {
        self::assertSame('50\\%', DivisionList::searchTerm(' 50% '));
        self::assertNull(DivisionList::searchTerm('   '));
        self::assertSame(64, mb_strlen((string)DivisionList::searchTerm(str_repeat('ї', 200))));
    }
}
