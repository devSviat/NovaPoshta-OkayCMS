<?php

namespace Okay\Modules\Sviat\NovaPoshta\Integration;

/** Накладна замовлення такою, як її бачать інші модулі; дати — 'Y-m-d H:i:s' за часом магазину. */
final readonly class NpShipment
{
    public function __construct(
        public int $orderId,
        public string $number,
        public ?int $statusCode,
        public ?string $statusAt,
        /** Коли посилка прибула у відділення; задано лише поки вона там чекає (статуси 7 і 8). */
        public ?string $arrivedAt,
        /** Отримувач платить при отриманні: контроль оплати або грошовий переказ. */
        public bool $cod,
    ) {
    }
}
