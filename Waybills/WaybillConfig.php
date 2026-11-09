<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** Who sends the waybills and what a new waybill form starts with. */
final readonly class WaybillConfig
{
    public const string DEFAULT_CUTOFF = '16:00';
    public const string DEFAULT_DESCRIPTION = 'Товари інтернет-магазину';

    /**
     * @param 'Recipient'|'Sender' $payerType where the form starts; a draft still makes the sender pay when the order total holds delivery
     * @param 'Cash'|'NonCash' $paymentMethod
     * @param 'Parcel'|'Cargo'|'Documents' $cargoType
     * @param 'Recipient'|'Sender' $codPayer who pays the money-transfer commission
     */
    public function __construct(
        public ?string $contactRef,
        public ?string $senderPhone,
        /** HH:MM; a waybill made at or after it is dated tomorrow. */
        public string $cutoff = self::DEFAULT_CUTOFF,
        public string $payerType = 'Recipient',
        public string $paymentMethod = 'Cash',
        public string $cargoType = 'Parcel',
        public string $codPayer = 'Recipient',
        public string $description = self::DEFAULT_DESCRIPTION,
        /** 'order' — «Замовлення №…», as the old module; 'text' — $description */
        public string $descriptionMode = 'order',
        /** AdditionalInformation: 'skus', 'names' or 'none' */
        public string $extraInfo = 'skus',
    ) {
    }
}
