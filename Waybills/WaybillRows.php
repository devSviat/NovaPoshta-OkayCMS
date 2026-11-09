<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** Where waybills of orders are kept: one per order. */
interface WaybillRows
{
    public function forOrder(int $orderId): ?object;

    public function orderOfNumber(string $number): ?int;

    /**
     * @param ?string $number null while the waybill is being made
     * @param 'new'|'old'|'attached'|null $api null while the waybill is being made
     * @param array<string, mixed> $services
     */
    public function add(int $orderId, ?string $number, ?string $ref, ?string $api, array $services): bool;

    /**
     * Takes the order for one create before Nova Poshta is asked: the unique order_id lets only
     * one of two simultaneous requests through. False when the order already has a row.
     */
    public function reserve(int $orderId): bool;

    /** Turns the reservation into the made waybill. False when it could not be written. */
    public function complete(int $orderId, string $number, ?string $ref, string $api, array $services): bool;

    /** Drops the reservation; a made or attached waybill stays. */
    public function release(int $orderId): void;

    /** Keeps the reservation of a create NP did not answer: the order waits for WaybillService::lookup(). */
    public function markUncertain(int $orderId): void;

    public function saveAudit(int $orderId, AuditResult $audit): void;

    public function remove(int $orderId): void;

    /** Status from the batch tracking; marks the row checked. */
    public function saveStatus(int $orderId, int $code, string $text, ?string $statusAt, ?string $scheduledAt): void;

    /** The full getStatusDocuments item for the card, and the status it carries. */
    public function saveTracking(int $orderId, array $document): void;

    /** Asked about and not found in the batch: checked now, nothing else changes. */
    public function markChecked(int $orderId): void;

    /** @return list<object> waybills made since $createdSince to ask about, the longest unchecked first */
    public function due(int $limit, int $page, string $createdSince): array;

    /**
     * @param list<int> $orderIds
     * @return array<int, object> by order id
     */
    public function forOrders(array $orderIds): array;

    /** @return list<int> order ids with a waybill of this number */
    public function ordersOfNumber(string $number): array;
}
