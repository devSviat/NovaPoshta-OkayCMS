<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/** A checked pickup-point choice for one order, or nothing when the delivery is not this module's. */
final readonly class CartSelection
{
    public function __construct(
        public ?object $delivery,
        public ?object $settlement,
        public ?object $division,
    ) {
    }

    public static function none(): self
    {
        return new self(null, null, null);
    }

    public function applies(): bool
    {
        return $this->settlement !== null && $this->division !== null;
    }
}
