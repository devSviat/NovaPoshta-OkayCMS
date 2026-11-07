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
    public function add(int $orderId, string $number, ?string $ref, string $api, array $services): bool;

    /**
     * Takes the order for one create before Nova Poshta is asked: the unique order_id lets only
     * one of two simultaneous requests through. False when the order already has a row.
     */
    public function reserve(int $orderId): bool;

    /** Turns the reservation into the made waybill. False when it could not be written. */
    public function complete(int $orderId, string $number, ?string $ref, string $api, array $services): bool;

    /** Drops the reservation; a made or attached waybill stays. */
    public function release(int $orderId): void;

    public function saveAudit(int $orderId, AuditResult $audit): void;

    public function remove(int $orderId): void;
}
