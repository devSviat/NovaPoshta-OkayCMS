<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyOrders;
use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity;

final class WaybillStore implements WaybillRows
{
    public function __construct(private readonly EntityFactory $entityFactory)
    {
    }

    public function forOrder(int $orderId): ?object
    {
        $row = $this->entity()->findOne(['order_id' => $orderId]);

        return is_object($row) ? $row : null;
    }

    /** Замовлення, до якого номер уже прив'язано, якщо таке є. */
    public function orderOfNumber(string $number): ?int
    {
        $row = $this->entity()->findOne(['number' => $number]);

        return is_object($row) ? (int)$row->order_id : null;
    }

    /**
     * @param ?string $number null, поки накладна створюється
     * @param 'new'|'old'|'attached'|null $api як її створено; null, поки вона створюється
     * @param array<string, mixed> $services що замовлено, для звірки і картки
     */
    public function add(int $orderId, ?string $number, ?string $ref, ?string $api, array $services): bool
    {
        $now = date('Y-m-d H:i:s');

        // Ядро ковтає помилку дубліката ключа (order_id або number) і повертає false.
        return (bool)$this->entity()->add([
            'order_id' => $orderId,
            'number' => $number,
            'ref' => $ref,
            'api' => $api,
            'services' => json_encode($services, JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function reserve(int $orderId): bool
    {
        return $this->add($orderId, null, null, null, []);
    }

    public function complete(int $orderId, string $number, ?string $ref, string $api, array $services): bool
    {
        $row = $this->forOrder($orderId);
        if (!PendingWaybill::is($row)) {
            return false;
        }
        $this->entity()->update((int)$row->id, [
            'number' => $number,
            'ref' => $ref,
            'api' => $api,
            'services' => json_encode($services, JSON_UNESCAPED_UNICODE),
            'audit_status' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $saved = $this->forOrder($orderId);

        return $saved !== null && $saved->number === $number;
    }

    public function release(int $orderId): void
    {
        $row = $this->forOrder($orderId);
        if (PendingWaybill::is($row)) {
            $this->entity()->delete([(int)$row->id]);
        }
    }

    public function markUncertain(int $orderId): void
    {
        $row = $this->forOrder($orderId);
        if (PendingWaybill::is($row)) {
            $this->entity()->update((int)$row->id, ['audit_status' => 'uncertain', 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    public function saveAudit(int $orderId, AuditResult $audit): void
    {
        $row = $this->forOrder($orderId);
        if ($row === null) {
            return;
        }
        $this->entity()->update((int)$row->id, [
            'audit_status' => $audit->status,
            'audit' => json_encode($audit->toArray(), JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function remove(int $orderId): void
    {
        $row = $this->forOrder($orderId);
        if ($row !== null) {
            $this->entity()->delete([(int)$row->id]);
        }
    }

    /**
     * Накладна, перенесена з NovaPoshtaTracking, зі статусом і трекінгом, які вона мала.
     * @param array{number:string, ref:?string, api:string, status_code:?int, status_text:?string, status_at:?string, scheduled_at:?string, tracking:?array, tracked_at:?string, created_at?:?string} $values
     * @return bool false, коли замовлення вже має накладну
     */
    /** @return LegacyOrders::DONE|LegacyOrders::EXISTED|LegacyOrders::FAILED */
    public function importLegacy(int $orderId, array $values): string
    {
        if ($this->forOrder($orderId) !== null) {
            return LegacyOrders::EXISTED;
        }
        // Номер перевірено раніше (waybillOwner), тож відмова тут — збій запису, а не дублікат.
        if (!$this->add($orderId, $values['number'], $values['ref'], $values['api'], [])) {
            return LegacyOrders::FAILED;
        }
        $this->update($orderId, [
            'status_code' => $values['status_code'],
            'status_text' => $values['status_text'],
            'status_at' => $values['status_at'],
            'scheduled_at' => $values['scheduled_at'],
            'tracking' => $values['tracking'] !== null ? json_encode($values['tracking'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
            'tracked_at' => $values['tracked_at'],
        ] + (($values['created_at'] ?? null) !== null ? ['created_at' => $values['created_at']] : []));

        return LegacyOrders::DONE;
    }

    public function saveStatus(int $orderId, int $code, string $text, ?string $statusAt, ?string $scheduledAt): void
    {
        $this->update($orderId, [
            'status_code' => $code,
            'status_text' => mb_substr($text, 0, 255),
            'status_at' => $statusAt,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public function saveTracking(int $orderId, array $document): void
    {
        $row = $this->forOrder($orderId);
        if ($row === null) {
            return;
        }
        $document = WaybillTrackingDocument::merge($document, is_string($row->tracking ?? null) ? json_decode($row->tracking, true) : null, date('Y-m-d H:i:s'));
        $this->update($orderId, ['tracking' => json_encode($document, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)] + WaybillTrackingDocument::statusColumns($document, $row));
    }

    public function markChecked(int $orderId): void
    {
        $this->update($orderId, []);
    }

    public function due(int $limit, int $page, string $createdSince): array
    {
        return array_values($this->entity()->order('due')->find(['due' => 1, 'created_since' => $createdSince, 'limit' => $limit, 'page' => $page]));
    }

    public function forOrders(array $orderIds): array
    {
        return $orderIds === [] ? [] : $this->entity()->mappedBy('order_id')->find(['order_id' => $orderIds, 'limit' => count($orderIds)]);
    }

    public function ordersOfNumber(string $number): array
    {
        return array_map(intval(...), array_values($this->entity()->cols(['order_id'])->noLimit()->find(['number' => $number])));
    }

    /** Кожен запис трекінгу також фіксує, коли рядок востаннє перевірено. */
    private function update(int $orderId, array $values): void
    {
        $row = $this->forOrder($orderId);
        if ($row !== null) {
            $now = date('Y-m-d H:i:s');
            $this->entity()->update((int)$row->id, $values + ['tracked_at' => $now, 'updated_at' => $now]);
        }
    }

    private function entity(): WaybillEntity
    {
        return $this->entityFactory->get(WaybillEntity::class);
    }
}
