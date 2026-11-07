<?php

namespace Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Orders\OrderParcel;
use Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume;
use PHPUnit\Framework\TestCase;

class OrderParcelTest extends TestCase
{
    private static function purchases(): array
    {
        return [
            (object)['amount' => 2, 'variant' => (object)['weight' => 1.5, VariantVolume::FIELD => null]],
            (object)['amount' => 1, 'variant' => (object)['weight' => 0, VariantVolume::FIELD => null]],
        ];
    }

    public function testAsCartCarriesPurchasesAndTotal(): void
    {
        $purchases = self::purchases();
        $cart = OrderParcel::asCart((object)['total_price' => '2454', 'delivery_price' => '95', 'separate_delivery' => 0], $purchases);

        self::assertSame(2359.0, $cart->total_price);
        self::assertSame($purchases, $cart->purchases);
        self::assertSame(2454.0, OrderParcel::asCart((object)['total_price' => 2454, 'delivery_price' => 95, 'separate_delivery' => 1], [])->total_price);
    }

    public function testMetricsComeFromOrderPurchases(): void
    {
        $metrics = OrderParcel::metricsOf(self::purchases(), 50.0, 40.0);

        self::assertSame(3.0, $metrics->knownWeight);
        self::assertSame(1, $metrics->unitsWithoutWeight);
        self::assertSame(50.0, $metrics->total);
        self::assertSame(2000.0, $metrics->totalUah);
    }
}
