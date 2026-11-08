<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

use Okay\Core\EntityFactory;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryStore;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStore;

final class LegacyOrdersDb implements LegacyOrders
{
    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly OrderDeliveryStore $deliveries,
        private readonly WaybillStore $waybills,
    ) {
    }

    public function order(int $orderId): ?object
    {
        $order = $this->entityFactory->get(OrdersEntity::class)->get($orderId);

        return $order ? (object)[
            'id' => (int)$order->id,
            'delivery_id' => (int)($order->delivery_id ?? 0),
            'delivery_price' => (float)($order->delivery_price ?? 0),
            'payment_method_id' => (int)($order->payment_method_id ?? 0),
        ] : null;
    }

    public function hasDelivery(int $orderId): bool
    {
        return $this->deliveries->forOrder($orderId) !== null;
    }

    public function importDelivery(int $orderId, int $deliveryId, array $values): bool
    {
        return $this->deliveries->importLegacy($orderId, $deliveryId, $values);
    }

    public function waybillOwner(string $number): ?int
    {
        return $this->waybills->orderOfNumber($number);
    }

    public function hasWaybill(int $orderId): bool
    {
        return $this->waybills->forOrder($orderId) !== null;
    }

    public function importWaybill(int $orderId, array $values): bool
    {
        return $this->waybills->importLegacy($orderId, $values);
    }
}
