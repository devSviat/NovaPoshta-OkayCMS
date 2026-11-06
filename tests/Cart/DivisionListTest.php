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

    public function testPageKeepsTheOrderItIsGivenAndExplainsUnfitPoints(): void
    {
        $page = DivisionList::page(
            [self::point(1, '1', 'PostBranch', ['max_weight' => '10.00']), self::point(2, '2', 'PostBranch')],
            new CartMetrics(12.4, false, 100), 'ua', 'friday',
        );

        self::assertSame([1, 2], array_column($page, 'id'), 'the order is decided by pageIds');
        self::assertSame(['too_heavy'], $page[0]['problems']);
        self::assertArrayNotHasKey('problems', $page[1], 'no problems → no field');
    }

    public function testSearchedNumberComesBeforeAddressMatches(): void
    {
        // "13": branch №13 first, then numbers starting with 13, then "вул. Слобожанська, 13" (author, 06.11.2026).
        $light = [
            (object)['id' => 1, 'max_weight' => '30', 'max_declared_cost' => '0', 'match_rank' => 2],
            (object)['id' => 3, 'max_weight' => '30', 'max_declared_cost' => '0', 'match_rank' => 2],
            (object)['id' => 13, 'max_weight' => '30', 'max_declared_cost' => '0', 'match_rank' => 0],
            (object)['id' => 130, 'max_weight' => '30', 'max_declared_cost' => '0', 'match_rank' => 1],
        ];

        self::assertSame([13, 130, 1, 3], DivisionList::pageIds($light, new CartMetrics(1, false, 100)));
    }

    public function testAnExactNumberThatDoesNotFitStillComesFirst(): void
    {
        $light = [
            (object)['id' => 3, 'max_weight' => '30', 'max_declared_cost' => '0', 'match_rank' => 2],
            (object)['id' => 13, 'max_weight' => '10', 'max_declared_cost' => '0', 'match_rank' => 0],
        ];

        self::assertSame([13, 3], DivisionList::pageIds($light, new CartMetrics(12, false, 100)), 'the customer asked for it; it is shown greyed with the reason');
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
        self::assertSame(['50\\%'], DivisionList::searchWords(' 50% '));
        self::assertNull(DivisionList::searchWords('   '));
        self::assertSame(64, mb_strlen(implode('', DivisionList::searchWords(str_repeat('ї', 200)) ?? [])));
    }

    public function testSearchIsSplitIntoWords(): void
    {
        self::assertSame(['13'], DivisionList::searchWords('№ 13'));
        self::assertSame(['13'], DivisionList::searchWords('№13'));
        self::assertSame(['Дорогожицька', '4'], DivisionList::searchWords('Дорогожицька 4'));
        self::assertSame(['Дорогожицька', '4'], DivisionList::searchWords('Дорогожицька, 4'));
        self::assertNull(DivisionList::searchWords(' № , '));
        self::assertCount(5, DivisionList::searchWords('а б в г д е є ж'));
    }

    public function testFittingPointsFromAnywhereInTheGroupFillThePageFirst(): void
    {
        $light = [];
        for ($i = 1; $i <= DivisionList::PAGE + 50; $i++) {
            $light[] = (object)['id' => $i, 'max_weight' => '10.00', 'max_declared_cost' => '100000.00'];
        }
        $light[] = (object)['id' => 900, 'max_weight' => '1000.00', 'max_declared_cost' => '100000.00'];
        $light[] = (object)['id' => 901, 'max_weight' => '0', 'max_declared_cost' => '0'];

        $ids = DivisionList::pageIds($light, new CartMetrics(35, false, 100));

        self::assertCount(DivisionList::PAGE, $ids);
        self::assertSame([900, 901, 1, 2], array_slice($ids, 0, 4), 'fitting first, number order kept inside each part');
    }
}
