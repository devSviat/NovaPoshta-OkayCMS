<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** Яку ціну доставки отримує замовлення: розрахунок сервера, жодної, якщо він не вдався, або ціну ядра (адмінки). */
final class PriceDecision
{
    /**
     * @param array{delivery_price: mixed, separate_delivery: mixed} $coreInfo DeliveriesHelper::prepareDeliveryPriceInfo після попередніх розширень
     * @param ?float $coreDefault що дає саме ядро (ціна способу або 0 після free_from)
     * @return array{info: array, source: ?string} source іде в знімок замовлення
     */
    public static function forOrder(?DeliveryQuote $quote, array $coreInfo, bool $freeByCore = false, ?float $coreDefault = null): array
    {
        if ($quote === null) {
            return ['info' => $coreInfo, 'source' => null];
        }
        // Інший модуль (безкоштовна доставка з промоакції) уже змінив ціну ядра: вирішує він.
        if ($coreDefault !== null && abs((float)($coreInfo['delivery_price'] ?? 0) - $coreDefault) > 0.001) {
            return ['info' => $coreInfo, 'source' => 'external'];
        }
        if ($freeByCore) {
            return ['info' => $coreInfo, 'source' => $quote->source];
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
