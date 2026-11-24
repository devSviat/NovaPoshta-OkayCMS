<?php

namespace Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Orders\FreePromiseBackfill;
use PHPUnit\Framework\TestCase;

class FreePromiseBackfillTest extends TestCase
{
    private const array ORDER = ['total_price' => 95000.0, 'delivery_price' => 0, 'separate_delivery' => 1, 'closed' => 0];

    private static function order(array $over = []): object
    {
        return (object)($over + self::ORDER);
    }

    private static function threshold(): object
    {
        return (object)['paid' => 1, 'free_from' => 90000, 'price' => 60];
    }

    public function testCartOrderPastTheThresholdWithoutWaybillIsMarked(): void
    {
        self::assertTrue(FreePromiseBackfill::wasPromised(self::order(), (object)['price_source' => 'new'], self::threshold(), false));
        self::assertTrue(FreePromiseBackfill::wasPromised(self::order(), (object)['price_source' => 'legacy'], self::threshold(), false), 'migrated cart order of the old module');
    }

    public function testFreeMethodCountsOnlyWhenItChargedNothing(): void
    {
        $freeMethod = (object)['paid' => 0, 'free_from' => 90000, 'price' => 60];

        self::assertTrue(FreePromiseBackfill::wasPromised(self::order(['total_price' => 5000.0]), (object)['price_source' => 'none'], $freeMethod, false));
        self::assertFalse(FreePromiseBackfill::wasPromised(self::order(['total_price' => 5060.0, 'delivery_price' => 60, 'separate_delivery' => 0]), (object)['price_source' => 'none'], $freeMethod, false), 'a "free" method that charged its price');
    }

    public function testNothingToMark(): void
    {
        $snap = (object)['price_source' => 'new'];

        self::assertFalse(FreePromiseBackfill::wasPromised(self::order(), $snap, self::threshold(), true), 'waybill already exists');
        self::assertFalse(FreePromiseBackfill::wasPromised(self::order(['closed' => 1]), $snap, self::threshold(), false), 'closed order');
        self::assertFalse(FreePromiseBackfill::wasPromised(self::order(), (object)['price_source' => null], self::threshold(), false), 'admin snapshot');
        self::assertFalse(FreePromiseBackfill::wasPromised(self::order(), (object)['price_source' => 'admin'], self::threshold(), false));
        self::assertFalse(FreePromiseBackfill::wasPromised(self::order(), (object)['price_source' => 'external'], self::threshold(), false), 'promo is read from the snapshot as is');
        self::assertFalse(FreePromiseBackfill::wasPromised(self::order(), (object)['price_source' => 'free'], self::threshold(), false), 'already marked');
        self::assertFalse(FreePromiseBackfill::wasPromised(self::order(), $snap, null, false), 'delivery method deleted');
        self::assertFalse(FreePromiseBackfill::wasPromised(self::order(['total_price' => 5000.0]), $snap, self::threshold(), false), 'below the threshold: our shop');
    }
}
