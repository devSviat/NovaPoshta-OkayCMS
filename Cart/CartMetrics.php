<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume;

/** What the cart says about the parcel, as far as it is known. */
final readonly class CartMetrics
{
    public function __construct(
        /** Sum of weights of the goods that have one: the parcel weighs at least this much. */
        public float $knownWeight,
        /** Some goods have no weight: the real parcel may be heavier. */
        public bool $weightUnknown,
        public float $total,
        /** Sum of volumes (m³) of the goods that have one: a lower bound like the weight. */
        public float $knownVolume = 0.0,
        public int $unitsWithoutWeight = 0,
        public int $unitsWithoutVolume = 0,
        /** The cart total in hryvnias, for Nova Poshta's limits; null when there is no UAH currency. */
        public ?float $totalUah = null,
    ) {
    }

    public function inUah(?float $totalUah): self
    {
        return new self($this->knownWeight, $this->weightUnknown, $this->total, $this->knownVolume, $this->unitsWithoutWeight, $this->unitsWithoutVolume, $totalUah);
    }

    /** @param object $cart Okay\Core\Cart::get() */
    public static function fromCart(object $cart): self
    {
        $weight = 0.0;
        $volume = 0.0;
        $noWeight = 0;
        $noVolume = 0;
        $weightUnknown = false;
        foreach ((array)($cart->purchases ?? []) as $purchase) {
            $amount = max(0, (int)($purchase->amount ?? 0));
            $unitWeight = $purchase->variant->weight ?? null;
            if (is_numeric($unitWeight) && (float)$unitWeight > 0) {
                $weight += (float)$unitWeight * $amount;
            } else {
                $noWeight += $amount;
                $weightUnknown = true;
            }
            $unitVolume = $purchase->variant->{VariantVolume::FIELD} ?? null;
            if (is_numeric($unitVolume) && (float)$unitVolume > 0) {
                $volume += (float)$unitVolume * $amount;
            } else {
                $noVolume += $amount;
            }
        }

        return new self(
            $weight,
            $weightUnknown,
            is_numeric($cart->total_price ?? null) ? (float)$cart->total_price : 0.0,
            $volume,
            $noWeight,
            $noVolume,
        );
    }
}
