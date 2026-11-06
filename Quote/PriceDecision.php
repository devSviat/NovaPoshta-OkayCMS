<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** Which delivery price an order gets: the server's calculation, or the core's (admin) price. */
final class PriceDecision
{
    /**
     * @param array{delivery_price: mixed, separate_delivery: mixed} $coreInfo DeliveriesHelper::prepareDeliveryPriceInfo
     * @return array{info: array, source: ?string} source goes to the order snapshot
     */
    public static function forOrder(?DeliveryQuote $quote, array $coreInfo, bool $freeByCore = false): array
    {
        if ($quote === null) {
            return ['info' => $coreInfo, 'source' => null];
        }
        if ($quote->price === null || $freeByCore) {
            return ['info' => $coreInfo, 'source' => $quote->source];
        }

        return ['info' => ['delivery_price' => $quote->price] + $coreInfo, 'source' => $quote->source];
    }
}
