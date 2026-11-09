<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

final readonly class QuoteRequest
{
    public function __construct(
        /** Технологія НП для способу доставки, напр. WarehouseWarehouse (settings.xml). */
        public string $serviceType,
        /** Рядки пунктів видачі: id, settlement_id. */
        public object $sender,
        /** Рядок пункту видачі (id, settlement_id); null для адресної доставки. */
        public ?object $recipient,
        public Parcel $parcel,
        public bool $cod,
        public CodKind $codKind,
        public bool $wantPrice,
        public bool $wantTerm,
        public ?AddressTarget $address = null,
    ) {
    }
}
