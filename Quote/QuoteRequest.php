<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

final readonly class QuoteRequest
{
    public function __construct(
        /** The delivery method's NP technology, e.g. WarehouseWarehouse (settings.xml). */
        public string $serviceType,
        /** Division rows: id, settlement_id. */
        public object $sender,
        public object $recipient,
        public Parcel $parcel,
        public bool $cod,
        public CodKind $codKind,
        public bool $wantPrice,
        public bool $wantTerm,
    ) {
    }
}
