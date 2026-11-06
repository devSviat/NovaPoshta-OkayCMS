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
}
