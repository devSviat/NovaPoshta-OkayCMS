<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Modules\Sviat\NovaPoshta\Waybills\AuditResult;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillRows;

final class InMemoryWaybillRows implements WaybillRows
{
    /** @var array<int, object> by order id */
    public array $rows = [];
    /** Another request took the order between the check and the reservation. */
    public bool $failReserve = false;
    /** The database refused the write after Nova Poshta made the waybill. */
    public bool $failComplete = false;
    public int $now = 1_900_000_000;

    public function forOrder(int $orderId): ?object
    {
        return $this->rows[$orderId] ?? null;
    }

    public function orderOfNumber(string $number): ?int
    {
        foreach ($this->rows as $orderId => $row) {
            if ($row->number === $number) {
                return $orderId;
            }
        }

        return null;
    }

    public function add(int $orderId, ?string $number, ?string $ref, ?string $api, array $services): bool
    {
        // Both are unique in the table; NULL numbers (waybills being made) repeat freely.
        if (isset($this->rows[$orderId]) || ($number !== null && $this->orderOfNumber($number) !== null)) {
            return false;
        }
        $this->rows[$orderId] = (object)['order_id' => $orderId, 'number' => $number, 'ref' => $ref, 'api' => $api, 'services' => json_encode($services), 'audit_status' => null, 'audit' => null, 'created_at' => date('Y-m-d H:i:s', $this->now)];

        return true;
    }

    public function reserve(int $orderId): bool
    {
        return !$this->failReserve && $this->add($orderId, null, null, null, []);
    }

    public function complete(int $orderId, string $number, ?string $ref, string $api, array $services): bool
    {
        if ($this->failComplete || !\Okay\Modules\Sviat\NovaPoshta\Waybills\PendingWaybill::is($this->rows[$orderId] ?? null)) {
            return false;
        }
        $this->rows[$orderId] = (object)(['number' => $number, 'ref' => $ref, 'api' => $api, 'services' => json_encode($services)] + (array)$this->rows[$orderId]);

        return true;
    }

    public function release(int $orderId): void
    {
        if (\Okay\Modules\Sviat\NovaPoshta\Waybills\PendingWaybill::is($this->rows[$orderId] ?? null)) {
            unset($this->rows[$orderId]);
        }
    }

    public function saveAudit(int $orderId, AuditResult $audit): void
    {
        $this->rows[$orderId]->audit_status = $audit->status;
        $this->rows[$orderId]->audit = json_encode($audit->toArray());
    }

    public function saveStatus(int $orderId, int $code, string $text, ?string $statusAt, ?string $scheduledAt): void
    {
        $this->rows[$orderId] = (object)(['status_code' => $code, 'status_text' => $text, 'status_at' => $statusAt, 'scheduled_at' => $scheduledAt, 'tracked_at' => date('Y-m-d H:i:s', $this->now)] + (array)$this->rows[$orderId]);
    }

    public function saveTracking(int $orderId, array $document): void
    {
        $previous = isset($this->rows[$orderId]->tracking) ? json_decode($this->rows[$orderId]->tracking, true) : null;
        $document = \Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillTrackingDocument::merge($document, $previous, date('Y-m-d H:i:s', $this->now));
        $this->rows[$orderId] = (object)(['tracking' => json_encode($document, JSON_UNESCAPED_UNICODE), 'tracked_at' => date('Y-m-d H:i:s', $this->now)]
            + \Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillTrackingDocument::statusColumns($document, $this->rows[$orderId]) + (array)$this->rows[$orderId]);
    }

    public function markChecked(int $orderId): void
    {
        $this->rows[$orderId]->tracked_at = date('Y-m-d H:i:s', $this->now);
    }

    public function due(int $limit, int $page): array
    {
        $closed = [...\Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStatus::FINAL, 3];
        $due = array_values(array_filter($this->rows, static fn(object $r): bool => !\Okay\Modules\Sviat\NovaPoshta\Waybills\PendingWaybill::is($r) && !in_array($r->status_code ?? null, $closed, true)));
        usort($due, static fn(object $a, object $b): int => [($a->tracked_at ?? ''), $a->order_id] <=> [($b->tracked_at ?? ''), $b->order_id]);

        return array_slice($due, ($page - 1) * $limit, $limit);
    }

    public function forOrders(array $orderIds): array
    {
        return array_intersect_key($this->rows, array_flip($orderIds));
    }

    public function ordersWithStatus(array $codes): array
    {
        return array_keys(array_filter($this->rows, static fn(object $r): bool => in_array($r->status_code ?? null, $codes, true)));
    }

    public function ordersOfNumber(string $number): array
    {
        return array_keys(array_filter($this->rows, static fn(object $r): bool => $r->number === $number));
    }

    public function remove(int $orderId): void
    {
        unset($this->rows[$orderId]);
    }
}
