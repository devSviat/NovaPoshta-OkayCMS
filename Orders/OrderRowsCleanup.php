<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity;

/**
 * The module's rows of orders the admin deletes. Only the rows: a waybill already made stays at
 * Nova Poshta — deleting the order is not a request to cancel the shipment.
 */
final class OrderRowsCleanup
{
    public function __construct(private readonly EntityFactory $entityFactory)
    {
    }

    /** @param list<int|string> $orderIds */
    public function forOrders(array $orderIds): void
    {
        $orderIds = array_values(array_unique(array_filter(array_map(intval(...), $orderIds))));
        if ($orderIds === []) {
            return;
        }
        foreach ([OrderDeliveryEntity::class, WaybillEntity::class] as $class) {
            $entity = $this->entityFactory->get($class);
            $ids = array_map(intval(...), $entity->noLimit()->cols(['id'])->find(['order_id' => $orderIds]));
            if ($ids !== []) {
                $entity->delete($ids);
            }
        }
    }
}
