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

    public function add(int $orderId, string $number, ?string $ref, string $api, array $services): bool
    {
        if (isset($this->rows[$orderId])) {
            return false;
        }
        $this->rows[$orderId] = (object)['order_id' => $orderId, 'number' => $number, 'ref' => $ref, 'api' => $api, 'services' => json_encode($services), 'audit_status' => null, 'audit' => null, 'created_at' => date('Y-m-d H:i:s', $this->now)];

        return true;
    }

    public function reserve(int $orderId): bool
    {
        return !$this->failReserve && $this->add($orderId, '', null, 'pending', []);
    }

    public function complete(int $orderId, string $number, ?string $ref, string $api, array $services): bool
    {
        if ($this->failComplete || ($this->rows[$orderId]->api ?? null) !== 'pending') {
            return false;
        }
        $this->rows[$orderId] = (object)(['number' => $number, 'ref' => $ref, 'api' => $api, 'services' => json_encode($services)] + (array)$this->rows[$orderId]);

        return true;
    }

    public function release(int $orderId): void
    {
        if (($this->rows[$orderId]->api ?? null) === 'pending') {
            unset($this->rows[$orderId]);
        }
    }

    public function saveAudit(int $orderId, AuditResult $audit): void
    {
        $this->rows[$orderId]->audit_status = $audit->status;
        $this->rows[$orderId]->audit = json_encode($audit->toArray());
    }

    public function remove(int $orderId): void
    {
        unset($this->rows[$orderId]);
    }
}
