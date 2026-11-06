<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/** What the cart says about the parcel, as far as it is known. */
final readonly class CartMetrics
{
    public function __construct(
        /** Sum of weights of the goods that have one: the parcel weighs at least this much. */
        public float $knownWeight,
        /** Some goods have no weight: the real parcel may be heavier. */
        public bool $weightUnknown,
        public float $total,
    ) {
    }

    /** @param object $cart Okay\Core\Cart::get() */
    public static function fromCart(object $cart): self
    {
        $weight = 0.0;
        $unknown = false;
        foreach ((array)($cart->purchases ?? []) as $purchase) {
            $unit = $purchase->variant->weight ?? null;
            if (is_numeric($unit) && (float)$unit > 0) {
                $weight += (float)$unit * max(0, (int)($purchase->amount ?? 0));
            } else {
                $unknown = true;
            }
        }

        return new self($weight, $unknown, is_numeric($cart->total_price ?? null) ? (float)$cart->total_price : 0.0);
    }
}
