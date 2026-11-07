<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** Where waybills of orders are kept: one per order. */
interface WaybillRows
{
    public function forOrder(int $orderId): ?object;

    public function orderOfNumber(string $number): ?int;

    /**
     * @param 'new'|'old'|'attached' $api
     * @param array<string, mixed> $services
     */
    public function add(int $orderId, string $number, ?string $ref, string $api, array $services): void;

    /** @param list<array<string, string>> $findings */
    public function saveAudit(int $orderId, string $status, array $findings): void;

    public function remove(int $orderId): void;
}
