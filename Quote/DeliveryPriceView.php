<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/**
 * Puts a calculated price on a cart delivery method the way the core does in
 * DeliveriesHelper::getCartDeliveriesList, so themes show it without knowing the module.
 */
final class DeliveryPriceView
{
    /** @param callable(float): string $convert Money::convert into the visitor's currency */
    public static function apply(object $delivery, float $price, float $cartTotal, callable $convert, string $sign, string $separateText, string $freeText): void
    {
        // The order gets 0 once the cart reaches free_from (DeliveriesHelper::prepareDeliveryPriceInfo).
        $free = !((float)($delivery->free_from ?? 0) > $cartTotal);
        $delivery->is_free_delivery = $free;
        $delivery->price = $free ? 0.0 : $price;
        if ($free) {
            $delivery->delivery_price_text = $freeText;
        } else {
            $delivery->delivery_price_text = $convert($price) . ' ' . $sign . (!empty($delivery->separate_payment) ? ', ' . $separateText : '');
        }
        $delivery->total_price_with_delivery = $cartTotal + ($free || !empty($delivery->separate_payment) ? 0.0 : $price);
    }

    /** Nova Poshta gave no price: nothing is added to the total, the text says the manager will tell. */
    public static function unpriced(object $delivery, float $cartTotal, string $text): void
    {
        if (!empty($delivery->is_free_delivery)) {
            return;
        }
        $delivery->is_free_delivery = false;
        $delivery->price = 0.0;
        $delivery->delivery_price_text = $text;
        $delivery->total_price_with_delivery = $cartTotal;
    }
}
