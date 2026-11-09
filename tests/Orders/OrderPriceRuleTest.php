<?php

namespace Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Orders\OrderPriceRule;
use PHPUnit\Framework\TestCase;

class OrderPriceRuleTest extends TestCase
{
    private static function order(array $override = []): object
    {
        return (object)($override + ['total_price' => 2454.0, 'delivery_price' => 95.0, 'separate_delivery' => 0, 'paid' => 0]);
    }

    private static function delivery(array $override = []): object
    {
        return (object)($override + ['free_from' => 999999]);
    }

    public function testUnpricedKeeps(): void
    {
        self::assertSame(['action' => 'keep', 'price' => null], OrderPriceRule::decide(self::order(), self::delivery(), null, true));
        self::assertSame('keep', OrderPriceRule::decide(self::order(), self::delivery(), null, false)['action']);
    }

    /** Правило ядра: 0 — безкоштовно для будь-якого замовлення, і сам поріг теж безкоштовний. */
    public function testFreeLikeTheCore(): void
    {
        self::assertSame('free', OrderPriceRule::decide(self::order(), self::delivery(['free_from' => 0]), 105.0, false)['action']);
        self::assertSame('free', OrderPriceRule::decide(self::order(), self::delivery(['free_from' => 2359]), 105.0, false)['action'], 'goods 2359 = free_from');
    }

    public function testFreeByThreshold(): void
    {
        self::assertSame(['action' => 'free', 'price' => 0.0], OrderPriceRule::decide(self::order(), self::delivery(['free_from' => 2000]), 105.0, false));
        self::assertSame('offer', OrderPriceRule::decide(self::order(), self::delivery(['free_from' => 3000]), 105.0, false)['action']);
    }

    public function testPaidOrderIsNotRepriced(): void
    {
        self::assertSame(['action' => 'show', 'price' => 105.0], OrderPriceRule::decide(self::order(['paid' => 1]), self::delivery(), 105.0, false));
    }

    public function testSeparateDeliveryIsAutomatic(): void
    {
        self::assertSame(['action' => 'auto', 'price' => 105.0], OrderPriceRule::decide(self::order(['separate_delivery' => 1, 'total_price' => 2359.0]), self::delivery(), 105.0, false));
    }

    public function testIncludedIsOffered(): void
    {
        self::assertSame(['action' => 'offer', 'price' => 105.0], OrderPriceRule::decide(self::order(), self::delivery(), 105.0, false));
    }

    public function testSameTariffChangesNothing(): void
    {
        self::assertSame('keep', OrderPriceRule::decide(self::order(), self::delivery(), 95.0, false)['action']);
    }
}
