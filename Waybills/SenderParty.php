<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** Власник ключа як відправник однієї накладної: старому API потрібні refs, новому — пункт видачі й телефон. */
final readonly class SenderParty
{
    public function __construct(
        public string $counterpartyRef,
        public string $contactRef,
        public string $contactName,
        public string $phone,
        public int $divisionId,
        /** `number` пункту видачі, який старий API приймає як SenderWarehouseIndex. */
        public string $divisionNumber,
    ) {
    }
}
