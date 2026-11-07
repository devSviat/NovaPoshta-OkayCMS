<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** The key owner as the sender of one waybill: the old API wants the refs, the new one the point and phone. */
final readonly class SenderParty
{
    public function __construct(
        public string $counterpartyRef,
        public string $contactRef,
        public string $contactName,
        public string $phone,
        public int $divisionId,
        /** The point's `number`, which the old API takes as SenderWarehouseIndex. */
        public string $divisionNumber,
    ) {
    }
}
