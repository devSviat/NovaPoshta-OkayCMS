<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** Яку ціну доставки отримує замовлення: розрахунок сервера, жодної, якщо він не вдався, або ціну ядра (адмінки). */
final class PriceDecision
{
    /** Джерело в знімку: кошик пообіцяв безкоштовну доставку; накладна довіряє лише цьому запису, а не порогу способу. */
    public const string FREE = 'free';

    /**
     * @param array{delivery_price: mixed, separate_delivery: mixed} $coreInfo DeliveriesHelper::prepareDeliveryPriceInfo після попередніх розширень
     * @param ?float $coreDefault що дає саме ядро (ціна способу або 0 після free_from)
     * @param bool $freeMethod спосіб типу «Безкоштовна»; ядро однаково бере ціну способу, тож обіцянка — лише коли вийшов 0
     * @return array{info: array, source: ?string} source іде в знімок замовлення
     */
    public static function forOrder(?DeliveryQuote $quote, array $coreInfo, bool $freeByCore = false, ?float $coreDefault = null, bool $freeMethod = false): array
    {
        // Інший модуль (безкоштовна доставка з промоакції) уже змінив ціну ядра: вирішує він.
        if ($quote !== null && $coreDefault !== null && abs((float)($coreInfo['delivery_price'] ?? 0) - $coreDefault) > 0.001) {
            return ['info' => $coreInfo, 'source' => 'external'];
        }
        if ($freeByCore) {
            return ['info' => $coreInfo, 'source' => self::FREE];
        }
        $decision = self::priced($quote, $coreInfo);
        if ($freeMethod && (float)($decision['info']['delivery_price'] ?? 0) <= 0 && !$quote?->unpriced()) {
            return ['info' => $decision['info'], 'source' => self::FREE];
        }

        return $decision;
    }

    /** @return array{info: array, source: ?string} */
    private static function priced(?DeliveryQuote $quote, array $coreInfo): array
    {
        if ($quote === null) {
            return ['info' => $coreInfo, 'source' => null];
        }
        if ($quote->unpriced()) {
            return ['info' => ['delivery_price' => 0.0] + $coreInfo, 'source' => $quote->source];
        }
        if ($quote->price === null) {
            return ['info' => $coreInfo, 'source' => $quote->source];
        }

        return ['info' => ['delivery_price' => $quote->price] + $coreInfo, 'source' => $quote->source];
    }
}
