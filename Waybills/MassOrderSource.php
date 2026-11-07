<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\OrdersEntity;

final class MassOrderSource implements MassOrders
{
    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly Module $module,
        private readonly WaybillDrafts $drafts,
    ) {
    }

    public function isOurs(int $orderId): bool
    {
        $order = $this->entityFactory->get(OrdersEntity::class)->get($orderId);
        $delivery = $order ? $this->entityFactory->get(DeliveriesEntity::class)->get((int)($order->delivery_id ?? 0)) : null;

        return $delivery && (int)$delivery->module_id === (int)$this->module->getModuleIdByNamespace(__CLASS__);
    }

    public function draft(int $orderId): WaybillDraft|string
    {
        return $this->drafts->forOrder($orderId);
    }
}
