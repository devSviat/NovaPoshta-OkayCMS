<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** Identifies what a quote was made for; any change in the cart makes a new key (design: no stale prices). */
final class QuoteKey
{
    public static function for(int $deliveryId, int $divisionId, bool $cod, object $cart): string
    {
        $lines = [];
        foreach ((array)($cart->purchases ?? []) as $purchase) {
            $lines[] = (int)($purchase->variant_id ?? $purchase->variant->id ?? 0) . ':' . (int)($purchase->amount ?? 0);
        }
        sort($lines);

        return sha1(implode('|', [$deliveryId, $divisionId, $cod ? 1 : 0, implode(',', $lines), number_format((float)($cart->total_price ?? 0), 2, '.', '')]));
    }
}
