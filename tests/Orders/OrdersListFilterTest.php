<?php

namespace Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Orders\OrdersListFilter;
use PHPUnit\Framework\TestCase;

class OrdersListFilterTest extends TestCase
{
    private static function apply(array $filter, ?string $group, ?string $keyword): array
    {
        $byNumber = static fn(string $number): array => $number === '20450000000001' ? [990061] : [];

        return OrdersListFilter::apply($filter, $group, $keyword, $byNumber);
    }

    public function testOnlyFourteenDigitsBecomeWaybillSearch(): void
    {
        self::assertSame(['id' => [990061]], self::apply(['keyword' => '2045 0000 0000 01'], null, '2045 0000 0000 01'));
        self::assertSame(['id' => [990061]], self::apply(['keyword' => '2045-0000-0000-01'], null, '2045-0000-0000-01'));
        foreach (['990061', 'Петренко', '0500000000', '+380500000000'] as $keyword) {
            self::assertSame(['keyword' => $keyword], self::apply(['keyword' => $keyword], null, $keyword), $keyword);
        }
    }

    public function testUnknownWaybillLeavesTheCoreSearch(): void
    {
        // Не накладна цього магазину: пошук ядра її лишає (штрихкод у назві товару, цифри в email).
        self::assertSame(['keyword' => '20459999999999'], self::apply(['keyword' => '20459999999999'], null, '20459999999999'));
    }

    /** Група статусів — це підзапит у SQL, а не id кожного відповідного замовлення (з часом їх десятки тисяч). */
    public function testStatusGroupIsAnEntityFilter(): void
    {
        self::assertSame(['status' => 1, 'np_status_codes' => [7, 8]], self::apply(['status' => 1], 'arrived', null));
        self::assertSame(['id' => [990062, 5], 'np_status_codes' => [7, 8]], self::apply(['id' => [990062, 5]], 'arrived', null));
        self::assertSame([], self::apply([], 'nonsense', null));
    }

    public function testWithoutWaybill(): void
    {
        self::assertSame(['np_without_waybill' => 1], self::apply([], 'none', null));
    }

    public function testNumberAndGroupTogether(): void
    {
        self::assertSame(['id' => [990061], 'np_status_codes' => [7, 8]], self::apply(['keyword' => '20450000000001'], 'arrived', '20450000000001'));
    }
}
