<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillRows;

final class InMemoryWaybillRows implements WaybillRows
{
    /** @var array<int, object> by order id */
    public array $rows = [];

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

    public function add(int $orderId, string $number, ?string $ref, string $api, array $services): void
    {
        $this->rows[$orderId] = (object)['order_id' => $orderId, 'number' => $number, 'ref' => $ref, 'api' => $api, 'services' => json_encode($services), 'audit_status' => null, 'audit' => null];
    }

    public function saveAudit(int $orderId, string $status, array $findings): void
    {
        $this->rows[$orderId]->audit_status = $status;
        $this->rows[$orderId]->audit = json_encode($findings);
    }

    public function remove(int $orderId): void
    {
        unset($this->rows[$orderId]);
    }
}
