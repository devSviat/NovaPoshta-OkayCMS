<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Замовлення магазину й власні рядки модуля — у тому вигляді, як їх пише перенесення. */
interface LegacyOrders
{
    /** Результат запису: перенесено, рядок уже був, або запис у базу не вдався. */
    public const string DONE = 'done';
    public const string EXISTED = 'existed';
    public const string FAILED = 'failed';

    /** @return object|null id, delivery_id, delivery_price, payment_method_id */
    public function order(int $orderId): ?object;

    public function hasDelivery(int $orderId): bool;

    /** @return self::DONE|self::EXISTED|self::FAILED */
    public function importDelivery(int $orderId, int $deliveryId, array $values): string;

    public function hasWaybill(int $orderId): bool;

    /** Замовлення, якому вже належить номер накладної: номер унікальний у таблиці модуля. */
    public function waybillOwner(string $number): ?int;

    /** @return self::DONE|self::EXISTED|self::FAILED */
    public function importWaybill(int $orderId, array $values): string;
}
