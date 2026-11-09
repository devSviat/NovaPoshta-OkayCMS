<?php

namespace Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Quote\Parcel;
use Okay\Modules\Sviat\NovaPoshta\Quote\ShippingConfig;
use PHPUnit\Framework\TestCase;

class ParcelTest extends TestCase
{
    private static function config(): ShippingConfig
    {
        return new ShippingConfig(1, 1.5, 0.002, false);
    }

    public function testUnitsWithoutWeightGetTheDefault(): void
    {
        $parcel = Parcel::fromMetrics(new CartMetrics(2.0, true, 1000.0, 0.0, 2, 3), self::config());

        self::assertEqualsWithDelta(5.0, $parcel->weightKg, 1e-9);
        self::assertSame(1000.0, $parcel->declaredCost);
    }

    public function testWeightHasAFloor(): void
    {
        self::assertSame(0.1, Parcel::fromMetrics(new CartMetrics(0.01, false, 10.0), self::config())->weightKg);
    }

    public function testVolumeKnownFromGoodsOrDefault(): void
    {
        self::assertSame(0.03, Parcel::fromMetrics(new CartMetrics(1.0, false, 10.0, 0.03, 0, 2), self::config())->volumeM3);
        self::assertSame(0.002, Parcel::fromMetrics(new CartMetrics(1.0, false, 10.0, 0.0, 0, 2), self::config())->volumeM3);
    }

    public function testCubeSideInMillimetres(): void
    {
        self::assertSame(100, new Parcel(1.0, 0.001, 1.0)->cubeSideMm());
        self::assertSame(10, new Parcel(1.0, 0.0, 1.0)->cubeSideMm(), 'never below 1 cm');
        self::assertSame(289, new Parcel(1.0, 0.024, 1.0)->cubeSideMm());
    }

    public function testDeclaredCostIsGivenSeparately(): void
    {
        self::assertSame(41000.0, Parcel::fromMetrics(new CartMetrics(1.0, false, 1000.0), self::config(), 41000.0)->declaredCost);
    }
}
