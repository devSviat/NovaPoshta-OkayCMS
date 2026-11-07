<?php

namespace Modules\Sviat\NovaPoshta\Legacy;

require_once __DIR__ . '/../Support/FakeLegacyPlaces.php';

use Modules\Sviat\NovaPoshta\Support\FakeLegacyPlaces;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyAddressMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyBudget;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyCityMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyDivisionMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyNames;
use PHPUnit\Framework\TestCase;

class LegacyMatchersTest extends TestCase
{
    private const string KYIV_REF = '8d5a980d-391c-11dd-90d9-001a92567626';

    private static function places(): FakeLegacyPlaces
    {
        $p = new FakeLegacyPlaces();
        $p->settlements = [
            (object)['id' => 1, 'name_uk' => 'місто Київ', 'area_uk' => 'Київська область', 'district_uk' => null, 'has_pickup' => 1],
            (object)['id' => 2, 'name_uk' => 'село Іванівка', 'area_uk' => 'Одеська область', 'district_uk' => 'Березівський район', 'has_pickup' => 1],
            (object)['id' => 3, 'name_uk' => 'село Іванівка', 'area_uk' => 'Одеська область', 'district_uk' => 'Захарівський район', 'has_pickup' => 1],
            (object)['id' => 4, 'name_uk' => "місто Кам'янець-Подільський", 'area_uk' => 'Хмельницька область', 'district_uk' => null, 'has_pickup' => 1],
        ];
        $p->divisions = [
            (object)['id' => 10, 'settlement_id' => 1, 'number' => '11/13', 'name_uk' => 'Відділення №13: вул. Дорогожицька, 4', 'category' => 'CargoBranch'],
            (object)['id' => 11, 'settlement_id' => 1, 'number' => '11/130', 'name_uk' => 'Відділення №130: вул. Тростянецька, 1', 'category' => 'Branch'],
            (object)['id' => 12, 'settlement_id' => 2, 'number' => '15/1', 'name_uk' => 'Відділення №1: вул. Центральна, 1', 'category' => 'Branch'],
        ];

        return $p;
    }

    public function testPlaceNamesCompareWithoutType(): void
    {
        self::assertSame(LegacyNames::place('місто Київ'), LegacyNames::place('Київ'));
        self::assertSame(LegacyNames::place("Кам'янець-Подільський"), LegacyNames::place('Камʼянець-Подільський'));
        self::assertSame(LegacyNames::area('Одеська'), LegacyNames::area('Одеська область'));
        self::assertSame(LegacyNames::district('Березівський'), LegacyNames::district('Березівський район'));
    }

    public function testNumberFromColumnOrName(): void
    {
        self::assertSame(13, LegacyNames::number(13, 'whatever'));
        self::assertSame(7, LegacyNames::number(null, 'Відділення №7 (до 30 кг): вул. Шевченка, 1'));
        self::assertNull(LegacyNames::number(null, 'Поштомат'));
    }

    public function testDivisionBySuffixThenAddress(): void
    {
        $places = self::places();
        self::assertSame(10, LegacyDivisionMatcher::pick($places->divisionsOf(1), 13, 'Відділення №13 (до 30 кг): вул. Дорогожицька, 4')?->id, '11/130 is not /13');

        $twins = [
            (object)['id' => 20, 'number' => '5/13', 'name_uk' => 'Відділення №13: просп. Миру, 2'],
            (object)['id' => 21, 'number' => '6/13', 'name_uk' => 'Відділення №13: вул. Лісова, 8'],
        ];
        self::assertSame(21, LegacyDivisionMatcher::pick($twins, 13, 'Відділення №13: вулиця Лісова, 8')?->id);
        self::assertNull(LegacyDivisionMatcher::pick($twins, 13, 'Відділення №13: вул. Нова, 1'), 'no address match → not guessed');
        self::assertNull(LegacyDivisionMatcher::pick($places->divisionsOf(1), 99, 'Відділення №99'));
    }

    public function testTwoNamesakesAreNotGuessed(): void
    {
        $matcher = new LegacyAddressMatcher(self::places());
        [$id, $why] = $matcher->match('Іванівка', 'Одеська', null);
        self::assertNull($id);
        self::assertSame('legacy_fail_namesakes', $why);
        self::assertSame([3, null], $matcher->match('Іванівка', 'Одеська', 'Захарівський'));
        self::assertSame([4, null], $matcher->match('Камʼянець-Подільський', 'Хмельницька', null));
        self::assertSame([null, 'legacy_fail_no_place'], $matcher->match('Неіснуюче', 'Одеська', null));
    }

    public function testCityByCacheNameThenApi(): void
    {
        $places = self::places();
        $places->cityRefs['ref-cached'] = 2;
        $matcher = new LegacyCityMatcher($places);
        $budget = new LegacyBudget(PHP_INT_MAX, 5);

        self::assertSame([2, null], $matcher->match('ref-cached', 'Іванівка', $budget), 'cache wins over the namesake problem');
        self::assertSame([1, null], $matcher->match(self::KYIV_REF, 'Київ', $budget), 'one Kyiv with points');
        self::assertSame(0, $places->apiCalls);

        $places->indexOfCity['ref-ivanivka'] = '15/1';
        self::assertSame([2, null], $matcher->match('ref-ivanivka', 'Іванівка', $budget), 'namesakes → NP tells the index');
        self::assertSame(1, $places->apiCalls);
        self::assertSame(2, $places->cityRefs['ref-ivanivka'], 'remembered for later');
        self::assertSame([null, 'legacy_fail_no_place'], $matcher->match('ref-gone', 'Іванівка', $budget));
    }

    public function testApiBudgetIsRespected(): void
    {
        $places = self::places();
        $places->indexOfCity['ref-ivanivka'] = '15/1';
        $matcher = new LegacyCityMatcher($places);

        self::assertSame([null, LegacyCityMatcher::BUDGET], $matcher->match('ref-ivanivka', 'Іванівка', new LegacyBudget(PHP_INT_MAX, 0)));
        self::assertSame(0, $places->apiCalls);
    }

    public function testUnreachableNpIsRetriedNotFailed(): void
    {
        $places = self::places();
        $places->indexOfCity['ref-ivanivka'] = 'down';

        self::assertSame([null, LegacyCityMatcher::BUDGET], (new LegacyCityMatcher($places))->match('ref-ivanivka', 'Іванівка', new LegacyBudget(PHP_INT_MAX, 5)));
    }
}
