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
        $free = FreeDelivery::applies($delivery, $cartTotal);
        $delivery->is_free_delivery = $free;
        $delivery->price = $free ? 0.0 : $price;
        if ($free) {
            $delivery->delivery_price_text = $freeText;
        } else {
            $delivery->delivery_price_text = $convert($price) . ' ' . $sign . (!empty($delivery->separate_payment) ? ', ' . $separateText : '');
        }
        $delivery->total_price_with_delivery = $cartTotal + ($free || !empty($delivery->separate_payment) ? 0.0 : $price);
    }

    /**
     * What the cart shows for a method: `price` — the quote; `unpriced` — NP gave none; `free` — the
     * order will be free though the core's cart says otherwise (it compares free_from strictly);
     * `awaiting` — no point or address chosen yet: the method's own price is not what NP will charge,
     * so none is shown or added to the total; `kept` — what the core or an earlier extender set.
     */
    public static function cartState(object $delivery, ?DeliveryQuote $quote, bool $own, bool $dictionaryReady, float $cartTotal): string
    {
        if (!empty($delivery->is_free_delivery)) {
            return 'kept';
        }
        if ($quote?->price !== null) {
            return 'price';
        }
        if ($quote?->unpriced()) {
            return 'unpriced';
        }
        if (!$own || $quote !== null) {
            return 'kept';
        }
        if (!empty($delivery->paid) && FreeDelivery::applies($delivery, $cartTotal)) {
            return 'free';
        }

        // Without dictionaries the customer cannot choose a point, and the order gets the method's own price.
        return $dictionaryReady ? 'awaiting' : 'kept';
    }

    public static function free(object $delivery, float $cartTotal, string $text): void
    {
        $delivery->is_free_delivery = true;
        $delivery->price = 0.0;
        $delivery->delivery_price_text = $text;
        $delivery->total_price_with_delivery = $cartTotal;
    }

    public static function awaiting(object $delivery, float $cartTotal, string $text): void
    {
        self::unpriced($delivery, $cartTotal, $text);
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
