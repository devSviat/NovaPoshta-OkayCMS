<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** The shop's orders and the module's own rows, as the migration writes them. */
interface LegacyOrders
{
    /** @return object|null id, delivery_id, delivery_price, payment_method_id */
    public function order(int $orderId): ?object;

    public function hasDelivery(int $orderId): bool;

    /** @return bool false when the order already has a row */
    public function importDelivery(int $orderId, int $deliveryId, array $values): bool;

    public function hasWaybill(int $orderId): bool;

    /** The order a waybill number already belongs to: a number is unique in the module's table. */
    public function waybillOwner(string $number): ?int;

    /** @return bool false when the order already has a waybill */
    public function importWaybill(int $orderId, array $values): bool;
}
