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

    public function testUnpricedDeliveryIsLeftOutOfTheTotal(): void
    {
        $d = (object)['free_from' => 5000, 'separate_payment' => 0, 'price' => 70];
        DeliveryPriceView::unpriced($d, 1250.0, 'уточнить менеджер');

        self::assertSame([0.0, 'уточнить менеджер', 1250.0, false], [$d->price, $d->delivery_price_text, $d->total_price_with_delivery, $d->is_free_delivery]);
    }

    public function testFreeDeliveryStaysFreeWhenUnpriced(): void
    {
        $d = (object)['free_from' => 0, 'separate_payment' => 0, 'price' => 70, 'is_free_delivery' => true, 'delivery_price_text' => 'безкоштовно'];
        DeliveryPriceView::unpriced($d, 1250.0, 'уточнить менеджер');

        self::assertSame('безкоштовно', $d->delivery_price_text);
    }

    /** Nothing chosen yet: no price the module did not get from NP, and nothing added to the total. */
    public function testAwaitingChoiceShowsNoPrice(): void
    {
        $d = (object)['free_from' => 999999, 'separate_payment' => 0, 'price' => 70, 'is_free_delivery' => false, 'delivery_price_text' => '70,00 грн', 'total_price_with_delivery' => 1320.0];
        DeliveryPriceView::awaiting($d, 1250.0, 'вартість — після вибору відділення');

        self::assertSame([0.0, 'вартість — після вибору відділення', 1250.0, false], [$d->price, $d->delivery_price_text, $d->total_price_with_delivery, $d->is_free_delivery]);
    }

    public function testFreeDeliveryStaysFreeWhileAwaiting(): void
    {
        $d = (object)['free_from' => 1000, 'separate_payment' => 0, 'price' => 70, 'is_free_delivery' => true, 'delivery_price_text' => 'безкоштовно'];
        DeliveryPriceView::awaiting($d, 1250.0, 'вартість — після вибору відділення');

        self::assertSame('безкоштовно', $d->delivery_price_text);
    }

    public function testCartStateOfOwnMethod(): void
    {
        $method = static fn(array $v = []): object => (object)($v + ['paid' => 1, 'free_from' => 2000, 'price' => 70]);
        $priced = new \Okay\Modules\Sviat\NovaPoshta\Quote\DeliveryQuote(95.0, null, 'new', null);

        self::assertSame('price', DeliveryPriceView::cartState($method(), $priced, true, true, 1000.0));
        self::assertSame('awaiting', DeliveryPriceView::cartState($method(), null, true, true, 1000.0));
        self::assertSame('kept', DeliveryPriceView::cartState($method(['is_free_delivery' => true]), null, true, true, 3000.0));
        self::assertSame('kept', DeliveryPriceView::cartState($method(), null, false, true, 1000.0), 'another module\'s method');
    }

    /** Without dictionaries nothing can be chosen: the cart shows the method's own price, the one the order gets. */
    public function testCartStateWithoutDictionariesKeepsCorePrice(): void
    {
        self::assertSame('kept', DeliveryPriceView::cartState((object)['paid' => 1, 'free_from' => 2000, 'price' => 70], null, true, false, 1000.0));
    }

    /** The core's cart says "not free" at exactly free_from, its order says free: the cart follows the order. */
    public function testCartStateAtTheThresholdIsFree(): void
    {
        $d = (object)['paid' => 1, 'free_from' => 1000, 'price' => 70, 'is_free_delivery' => false];

        self::assertSame('free', DeliveryPriceView::cartState($d, null, true, true, 1000.0));
        DeliveryPriceView::free($d, 1000.0, 'безкоштовно');
        self::assertTrue($d->is_free_delivery);
        self::assertSame(['безкоштовно', 1000.0, 0.0], [$d->delivery_price_text, $d->total_price_with_delivery, $d->price]);
    }
}
