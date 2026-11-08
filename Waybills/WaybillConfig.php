<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** Who sends the waybills and what a new waybill form starts with. */
final readonly class WaybillConfig
{
    public const string DEFAULT_CUTOFF = '16:00';
    public const string DEFAULT_DESCRIPTION = 'Товари інтернет-магазину';

    /**
     * @param 'auto'|'Recipient'|'Sender' $payerType who pays delivery; auto — the sender when the order total already holds it
     * @param 'Cash'|'NonCash' $paymentMethod
     * @param 'Parcel'|'Cargo'|'Documents' $cargoType
     * @param 'Recipient'|'Sender' $codPayer who pays the money-transfer commission
     */
    public function __construct(
        public ?string $contactRef,
        public ?string $senderPhone,
        /** HH:MM; a waybill made at or after it is dated tomorrow. */
        public string $cutoff = self::DEFAULT_CUTOFF,
        public string $payerType = 'auto',
        public string $paymentMethod = 'Cash',
        public string $cargoType = 'Parcel',
        public string $codPayer = 'Recipient',
        public string $description = self::DEFAULT_DESCRIPTION,
    ) {
    }
}
