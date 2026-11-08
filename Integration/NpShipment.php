<?php

namespace Okay\Modules\Sviat\NovaPoshta\Integration;

/** An order's waybill as other modules see it; dates are 'Y-m-d H:i:s' in the shop's time. */
final readonly class NpShipment
{
    public function __construct(
        public int $orderId,
        public string $number,
        public ?int $statusCode,
        public ?string $statusAt,
        /** When the parcel reached the branch; set only while it waits there (statuses 7 and 8). */
        public ?string $arrivedAt,
        /** The recipient pays on pickup: payment control or money transfer. */
        public bool $cod,
    ) {
    }
}
