<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;

/** The parcel the price is calculated for: unlike the fit rules, defaults fill what goods do not say. */
final readonly class Parcel
{
    private const float MIN_WEIGHT = 0.1;
    private const int MIN_SIDE_MM = 10;

    public function __construct(
        public float $weightKg,
        public float $volumeM3,
        public float $declaredCost,
    ) {
    }

    /** @param ?float $declaredCost in hryvnias; the cart total is in the shop's main currency */
    public static function fromMetrics(CartMetrics $cart, ShippingConfig $config, ?float $declaredCost = null): self
    {
        return new self(
            max(self::MIN_WEIGHT, $cart->knownWeight + $config->defaultWeight * $cart->unitsWithoutWeight),
            $cart->knownVolume > 0 ? $cart->knownVolume : $config->defaultVolume,
            $declaredCost ?? $cart->total,
        );
    }

    /** The new API wants sizes, goods only have a volume: a cube of that volume. */
    public function cubeSideMm(): int
    {
        // Rounded up: a smaller cube would understate the volume and the price.
        return max(self::MIN_SIDE_MM, (int)ceil(1000 * $this->volumeM3 ** (1 / 3) - 1e-9));
    }
}
