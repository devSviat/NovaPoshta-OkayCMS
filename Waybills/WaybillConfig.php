<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** Хто відправляє накладні і з чого починається форма нової накладної. */
final readonly class WaybillConfig
{
    public const string DEFAULT_CUTOFF = '16:00';
    public const string DEFAULT_DESCRIPTION = 'Товари інтернет-магазину';
    /** Типи вантажу, які модуль уміє створювати, у порядку форми; усі ввімкнені, доки налаштування не скажуть інакше. */
    public const array CARGO_TYPES = ['Parcel', 'Cargo', 'Documents'];

    /**
     * @param 'Recipient'|'Sender' $payerType з чого починає форма; чернетка однаково робить платником відправника, коли сума замовлення містить доставку
     * @param 'Cash'|'NonCash' $paymentMethod
     * @param 'Parcel'|'Cargo'|'Documents' $cargoType
     * @param 'Recipient'|'Sender' $codPayer хто платить комісію за грошовий переказ
     * @param list<'Parcel'|'Cargo'|'Documents'> $cargoTypes типи, які пропонує форма накладної; містить $cargoType
     */
    public function __construct(
        public ?string $contactRef,
        public ?string $senderPhone,
        /** HH:MM; накладна, створена о цій порі чи пізніше, датується завтрашнім днем. */
        public string $cutoff = self::DEFAULT_CUTOFF,
        public string $payerType = 'Recipient',
        public string $paymentMethod = 'Cash',
        public string $cargoType = 'Parcel',
        public string $codPayer = 'Recipient',
        public string $description = self::DEFAULT_DESCRIPTION,
        /** 'order' — «Замовлення №…», як у старому модулі; 'text' — $description */
        public string $descriptionMode = 'order',
        /** AdditionalInformation: 'skus', 'names' або 'none' */
        public string $extraInfo = 'skus',
        public array $cargoTypes = self::CARGO_TYPES,
    ) {
    }
}
