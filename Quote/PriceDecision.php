<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** Which delivery price an order gets: the server's calculation, none when it failed, or the core's (admin) price. */
final class PriceDecision
{
    /**
     * @param array{delivery_price: mixed, separate_delivery: mixed} $coreInfo DeliveriesHelper::prepareDeliveryPriceInfo after earlier extenders
     * @param ?float $coreDefault what the core alone gives (the method's price, or 0 past free_from)
     * @return array{info: array, source: ?string} source goes to the order snapshot
     */
    public static function forOrder(?DeliveryQuote $quote, array $coreInfo, bool $freeByCore = false, ?float $coreDefault = null): array
    {
        if ($quote === null) {
            return ['info' => $coreInfo, 'source' => null];
        }
        // Another module (a promo's free shipping) already changed the core's price: it decides.
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
