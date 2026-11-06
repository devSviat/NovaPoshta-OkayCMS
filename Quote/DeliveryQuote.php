<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

final readonly class DeliveryQuote
{
    public function __construct(
        public ?float $price,
        public ?\DateTimeImmutable $term,
        /** new | old — which API priced it; none — no price was asked; fallback — it failed. */
        public string $source,
        /** null | unreachable | rejected | no_city_ref */
        public ?string $problem,
        /** Part of the price that is Nova Poshta's commission for transferring the COD money. */
        public ?float $codFee = null,
        /** Extra cost of floor lifting when it is not free (address delivery). */
        public ?float $liftingFee = null,
    ) {
    }
}
