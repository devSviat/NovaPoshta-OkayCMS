<?php

namespace Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Quote\DeliveryPriceView;
use PHPUnit\Framework\TestCase;

/** The same numbers and text the core gives a delivery method in the cart (DeliveriesHelper). */
class DeliveryPriceViewTest extends TestCase
{
    private static function apply(array $delivery, float $price, float $cartTotal): object
    {
        $d = (object)($delivery + ['free_from' => 0, 'separate_payment' => 0, 'price' => 50]);
        DeliveryPriceView::apply($d, $price, $cartTotal, static fn(float $p): string => number_format($p, 2, '.', ' '), '₴', 'оплачується окремо', 'безкоштовно');

        return $d;
    }

    public function testPriceAddsToTheTotal(): void
    {
        $d = self::apply(['free_from' => 5000], 95.0, 1250.0);

        self::assertSame([95.0, '95.00 ₴', 1345.0, false], [$d->price, $d->delivery_price_text, $d->total_price_with_delivery, $d->is_free_delivery]);
    }

    public function testSeparatePaymentKeepsTheTotal(): void
    {
        $d = self::apply(['free_from' => 5000, 'separate_payment' => 1], 95.0, 1250.0);

        self::assertSame(['95.00 ₴, оплачується окремо', 1250.0], [$d->delivery_price_text, $d->total_price_with_delivery]);
    }

    public function testFreeFromIsTheCoresRule(): void
    {
        $d = self::apply(['free_from' => 1000], 95.0, 1250.0);

        self::assertSame([0.0, 'безкоштовно', 1250.0, true], [$d->price, $d->delivery_price_text, $d->total_price_with_delivery, $d->is_free_delivery]);
    }
}
