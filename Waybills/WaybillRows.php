<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** Де зберігаються накладні замовлень: одна на замовлення. */
interface WaybillRows
{
    public function forOrder(int $orderId): ?object;

    public function orderOfNumber(string $number): ?int;

    /**
     * @param ?string $number null, поки накладна створюється
     * @param 'new'|'old'|'attached'|null $api null, поки накладна створюється
     * @param array<string, mixed> $services
     */
    public function add(int $orderId, ?string $number, ?string $ref, ?string $api, array $services): bool;

    /**
     * Займає замовлення для одного створення до запиту до Нової Пошти: унікальний order_id пропускає лише
     * один із двох одночасних запитів. False, коли замовлення вже має рядок.
     */
    public function reserve(int $orderId): bool;

    /** Перетворює резервацію на створену накладну. False, коли її не вдалося записати. */
    public function complete(int $orderId, string $number, ?string $ref, string $api, array $services): bool;

    /** Знімає резервацію; створена або прив'язана накладна лишається. */
    public function release(int $orderId): void;

    /** Зберігає резервацію створення, на яке НП не відповіла: замовлення чекає на WaybillService::lookup(). */
    public function markUncertain(int $orderId): void;

    public function saveAudit(int $orderId, AuditResult $audit): void;

    public function remove(int $orderId): void;

    /** Статус із пакетного трекінгу; позначає рядок перевіреним. */
    public function saveStatus(int $orderId, int $code, string $text, ?string $statusAt, ?string $scheduledAt): void;

    /** Повний елемент getStatusDocuments для картки і статус, який він містить. */
    public function saveTracking(int $orderId, array $document): void;

    /** Запитано й не знайдено в пакеті: перевірено зараз, більше нічого не змінюється. */
    public function markChecked(int $orderId): void;

    /** @return list<object> накладні, створені після $createdSince, про які треба запитати, найдовше неперевірені — першими */
    public function due(int $limit, int $page, string $createdSince): array;

    /**
     * @param list<int> $orderIds
     * @return array<int, object> за id замовлення
     */
    public function forOrders(array $orderIds): array;

    /** @return list<int> id замовлень із накладною з цим номером */
    public function ordersOfNumber(string $number): array;
}
