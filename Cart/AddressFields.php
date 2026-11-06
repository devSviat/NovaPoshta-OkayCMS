<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/** A checked delivery address from the cart (design §6). */
final readonly class AddressFields
{
    public function __construct(
        public int $settlementId,
        public string $street,
        /** Old API street Ref when the customer picked a suggestion; typed streets have none. */
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
