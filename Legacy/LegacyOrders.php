<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Замовлення магазину й власні рядки модуля — у тому вигляді, як їх пише перенесення. */
interface LegacyOrders
{
    /** @return object|null id, delivery_id, delivery_price, payment_method_id */
    public function order(int $orderId): ?object;

    public function hasDelivery(int $orderId): bool;

    /** @return bool false, коли замовлення вже має рядок */
    public function importDelivery(int $orderId, int $deliveryId, array $values): bool;

    public function hasWaybill(int $orderId): bool;

    /** Замовлення, якому вже належить номер накладної: номер унікальний у таблиці модуля. */
    public function waybillOwner(string $number): ?int;

    /** @return bool false, коли замовлення вже має накладну */
    public function importWaybill(int $orderId, array $values): bool;
}
