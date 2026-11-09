<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;

/** Загальні для магазину значення, з яких рахується ціна доставки. */
final readonly class ShippingConfig
{
    public const float DEFAULT_WEIGHT = 1.0;
    public const float DEFAULT_VOLUME = 0.001;

    public function __construct(
        public ?int $senderDivisionId,
        public float $defaultWeight,
        public float $defaultVolume,
        public bool $showTerm,
        /** Пропонувати в кошику платний підйом на поверх для адресної доставки (типово вимкнено). */
        public bool $showLifting = false,
    ) {
    }

    /** @param callable(string): mixed $get Settings::get */
    public static function fromSettings(callable $get): self
    {
        $sender = (int)$get(ShippingSettings::SENDER);
        $weight = (float)$get(ShippingSettings::WEIGHT);
        $volume = (float)$get(ShippingSettings::VOLUME);

        return new self(
            $sender > 0 ? $sender : null,
            $weight > 0 ? $weight : self::DEFAULT_WEIGHT,
            $volume > 0 ? $volume : self::DEFAULT_VOLUME,
            (bool)$get(ShippingSettings::SHOW_TERM),
            (bool)$get(ShippingSettings::SHOW_LIFTING),
        );
    }
}
