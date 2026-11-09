<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;

/**
 * Посилка, для якої рахується ціна: на відміну від правил вміщення, те, чого не кажуть товари,
 * заповнюють типові значення.
 */
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

    /** @param ?float $declaredCost у гривнях; сума кошика — в основній валюті магазину */
    public static function fromMetrics(CartMetrics $cart, ShippingConfig $config, ?float $declaredCost = null): self
    {
        return new self(
            max(self::MIN_WEIGHT, $cart->knownWeight + $config->defaultWeight * $cart->unitsWithoutWeight),
            $cart->knownVolume > 0 ? $cart->knownVolume : $config->defaultVolume,
            $declaredCost ?? $cart->total,
        );
    }

    /** Новий API хоче розміри, а в товарів є лише об'єм: куб такого об'єму. */
    public function cubeSideMm(): int
    {
        // Округлення вгору: менший куб занизив би об'єм і ціну.
        return max(self::MIN_SIDE_MM, (int)ceil(1000 * $this->volumeM3 ** (1 / 3) - 1e-9));
    }
}
