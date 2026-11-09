<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

final readonly class DeliveryQuote
{
    public function __construct(
        public ?float $price,
        public ?\DateTimeImmutable $term,
        /** new | old — which API priced it; none — no price was asked; fallback — it failed. */
        public string $source,
        /** null | unreachable | rejected | no_city_ref | no_uah | busy | no_sender */
        public ?string $problem,
        /** Nova Poshta's commission for transferring the COD money: not in the price, the recipient pays it at the branch. */
        public ?float $codFee = null,
        /** Extra cost of floor lifting when it is not free (address delivery). */
        public ?float $liftingFee = null,
        /** Nova Poshta does not lift this parcel at all (e.g. "Weight is too high for floors lifting"). */
        public bool $liftingRefused = false,
    ) {
    }

    /**
     * Nova Poshta was asked and gave no price: the customer is not charged for delivery, the manager
     * states it later. "busy" is not one: checkout prices it anyway.
     */
    public function unpriced(): bool
    {
        return $this->price === null && $this->source === 'fallback' && $this->problem !== 'busy';
    }
}
