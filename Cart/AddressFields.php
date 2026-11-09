<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/** Перевірена адреса доставки з кошика. */
final readonly class AddressFields
{
    public function __construct(
        public int $settlementId,
        public string $street,
        /** Ref вулиці зі старого API, коли покупець обрав підказку; у вулиць, введених вручну, його немає. */
        public ?string $streetRef,
        public string $house,
        public ?string $flat,
        public ?string $entrance,
        public ?int $floor,
        public bool $elevator,
        public ?string $note,
        public bool $lifting,
    ) {
    }
}
