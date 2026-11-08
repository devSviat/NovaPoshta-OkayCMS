<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

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

    /** The order a number is already attached to, if any. */
    public function orderOfNumber(string $number): ?int
    {
        $row = $this->entity()->findOne(['number' => $number]);

        return is_object($row) ? (int)$row->order_id : null;
    }

    /**
     * @param ?string $number null while the waybill is being made
     * @param 'new'|'old'|'attached'|null $api how it was made; null while it is being made
     * @param array<string, mixed> $services what was ordered, for the audit and the card
     */
    public function add(int $orderId, ?string $number, ?string $ref, ?string $api, array $services): bool
    {
        $now = date('Y-m-d H:i:s');

        // The core swallows the duplicate-key error (order_id or number) and returns false.
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
     * A waybill carried over from NovaPoshtaTracking (stage 5), with the status and tracking it had.
     * @param array{number:string, ref:?string, api:string, status_code:?int, status_text:?string, status_at:?string, scheduled_at:?string, tracking:?array, tracked_at:?string} $values
     * @return bool false when the order already has a waybill
     */
    public function importLegacy(int $orderId, array $values): bool
    {
        if ($this->forOrder($orderId) !== null || !$this->add($orderId, $values['number'], $values['ref'], $values['api'], [])) {
            return false;
        }
        $this->update($orderId, [
            'status_code' => $values['status_code'],
            'status_text' => $values['status_text'],
            'status_at' => $values['status_at'],
            'scheduled_at' => $values['scheduled_at'],
            'tracking' => $values['tracking'] !== null ? json_encode($values['tracking'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
            'tracked_at' => $values['tracked_at'],
        ]);

        return true;
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

    public function due(int $limit, int $page): array
    {
        return array_values($this->entity()->order('due')->find(['due' => 1, 'limit' => $limit, 'page' => $page]));
    }

    public function forOrders(array $orderIds): array
    {
        return $orderIds === [] ? [] : $this->entity()->mappedBy('order_id')->find(['order_id' => $orderIds, 'limit' => count($orderIds)]);
    }

    public function ordersWithStatus(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        return array_map('intval', array_values($this->entity()->cols(['order_id'])->noLimit()->find(['status_codes' => $codes])));
    }

    public function ordersOfNumber(string $number): array
    {
        return array_map('intval', array_values($this->entity()->cols(['order_id'])->noLimit()->find(['number' => $number])));
    }

    /** Every tracking write also says when the row was last checked. */
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
