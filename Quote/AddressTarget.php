<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** Address delivery as a quote target: the settlement's old city Ref and the floor options. */
final readonly class AddressTarget
{
    public function __construct(
        public int $settlementId,
        public string $cityRef,
        public bool $lifting,
        public ?int $floor,
        public bool $elevator,
        /** The cart may offer lifting: price it too, to show its extra cost. */
        public bool $offerLifting,
    ) {
    }
}
