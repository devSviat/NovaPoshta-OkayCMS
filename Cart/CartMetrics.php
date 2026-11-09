<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume;

/** Що кошик каже про посилку, наскільки це відомо. */
final readonly class CartMetrics
{
    public function __construct(
        /** Сума ваг товарів, у яких вона є: посилка важить щонайменше стільки. */
        public float $knownWeight,
        /** Деякі товари без ваги: справжня посилка може бути важчою. */
        public bool $weightUnknown,
        public float $total,
        /** Сума об'ємів (m³) товарів, у яких він є: нижня межа, як і вага. */
        public float $knownVolume = 0.0,
        public int $unitsWithoutWeight = 0,
        public int $unitsWithoutVolume = 0,
        /** Сума кошика в гривнях, для лімітів Нової Пошти; null, коли валюти UAH немає. */
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
        return self::fromPurchases((array)($cart->purchases ?? []), is_numeric($cart->total_price ?? null) ? (float)$cart->total_price : 0.0);
    }

    /** @param iterable<object> $purchases кожен з `amount` і `variant` (weight, volume) */
    public static function fromPurchases(iterable $purchases, float $total): self
    {
        $weight = 0.0;
        $volume = 0.0;
        $noWeight = 0;
        $noVolume = 0;
        $weightUnknown = false;
        foreach ($purchases as $purchase) {
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
            $total,
            $volume,
            $noWeight,
            $noVolume,
        );
    }
}
