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
     * @param 'new'|'old'|'attached' $api
     * @param array<string, mixed> $services what was ordered, for the audit and the card
     */
    public function add(int $orderId, string $number, ?string $ref, string $api, array $services): void
    {
        $now = date('Y-m-d H:i:s');
        $this->entity()->add([
            'order_id' => $orderId,
            'number' => $number,
            'ref' => $ref,
            'api' => $api,
            'services' => json_encode($services, JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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

    private function entity(): WaybillEntity
    {
        return $this->entityFactory->get(WaybillEntity::class);
    }
}
