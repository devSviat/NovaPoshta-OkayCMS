<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/**
 * Ставить розраховану ціну на спосіб доставки в кошику так само, як ядро в
 * DeliveriesHelper::getCartDeliveriesList, щоб теми показували її, нічого не знаючи про модуль.
 */
final class DeliveryPriceView
{
    /** @param callable(float): string $convert Money::convert у валюту відвідувача */
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
     * Що кошик показує для способу: `price` — розрахунок ціни; `unpriced` — НП ціни не дала; `free` —
     * замовлення буде безкоштовним, хоча кошик ядра каже інакше (він порівнює free_from строго);
     * `awaiting` — пункт видачі чи адресу ще не обрано: власна ціна способу — не те, що візьме НП,
     * тож нічого не показується й не додається до суми; `kept` — те, що встановило ядро або попередній extender.
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

        // Без довідників покупець не може обрати пункт видачі, і замовлення отримує власну ціну способу.
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

    /** Нова Пошта не дала ціни: до суми нічого не додається, текст каже, що повідомить менеджер. */
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
