<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use PHPUnit\Framework\TestCase;

class CartMetricsTest extends TestCase
{
    private static function purchase(mixed $weight, int $amount): object
    {
        return (object)['amount' => $amount, 'variant' => (object)['weight' => $weight]];
    }

    public function testKnownWeightIsALowerBound(): void
    {
        $metrics = CartMetrics::fromCart((object)[
            'purchases' => [self::purchase('2.5', 2), self::purchase(null, 1), self::purchase('0', 3), self::purchase(1.2, 1)],
            'total_price' => '1999.90',
        ]);

        self::assertEqualsWithDelta(6.2, $metrics->knownWeight, 1e-9);
        self::assertTrue($metrics->weightUnknown);
        self::assertSame(1999.9, $metrics->total);
    }

    public function testAllWeightsKnown(): void
    {
        $metrics = CartMetrics::fromCart((object)['purchases' => [self::purchase(3, 1)], 'total_price' => 10]);

        self::assertFalse($metrics->weightUnknown);
    }

    public function testEmptyOrBrokenCart(): void
    {
        $metrics = CartMetrics::fromCart((object)['purchases' => null, 'total_price' => null]);

        self::assertSame(0.0, $metrics->knownWeight);
        self::assertSame(0.0, $metrics->total);
    }

    public function testNegativeOrGarbageWeightIsUnknown(): void
    {
        $metrics = CartMetrics::fromCart((object)['purchases' => [self::purchase(-5, 1), self::purchase('abc', 1)], 'total_price' => 1]);

        self::assertSame(0.0, $metrics->knownWeight);
        self::assertTrue($metrics->weightUnknown);
    }
}
