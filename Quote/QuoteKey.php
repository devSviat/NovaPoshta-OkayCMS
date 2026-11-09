<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** Identifies what a quote was made for; any change in the cart makes a new key, so no stale price is shown. */
final class QuoteKey
{
    /** Bumped when a cached quote's meaning changes. */
    private const string FORMAT = 'v3';

    /**
     * @param string $target "d:<division id>" or "a:<settlement id>"
     * @param ?int $floor with $elevator, part of the key only with lifting: NP prices lifting by both
     */
    public static function for(int $deliveryId, string $target, bool $cod, object $cart, bool $lifting = false, ?int $floor = null, bool $elevator = false): string
    {
        $lines = [];
        foreach ((array)($cart->purchases ?? []) as $purchase) {
            $lines[] = (int)($purchase->variant_id ?? $purchase->variant->id ?? 0) . ':' . (int)($purchase->amount ?? 0);
        }
        sort($lines);

        $lift = $lifting ? '1:' . (int)$floor . ':' . ($elevator ? 1 : 0) : '0';

        return sha1(implode('|', [self::FORMAT, $deliveryId, $target, $cod ? 1 : 0, $lift, implode(',', $lines), number_format((float)($cart->total_price ?? 0), 2, '.', '')]));
    }
}
