<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Core\EntityFactory;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity;
use Okay\Modules\Sviat\NovaPoshta\Quote\FreeDelivery;
use Okay\Modules\Sviat\NovaPoshta\Quote\PriceDecision;

/**
 * До 1.3.0 кошик не записував обіцянку безкоштовної доставки, а накладна перевіряла поріг способу заново.
 * Оформленим, але ще не відправленим замовленням позначка ставиться один раз за тим самим правилом,
 * інакше покупцю, якому кошик пообіцяв безкоштовну доставку, НП виставила б рахунок.
 */
final class FreePromiseBackfill
{
    private const int BATCH = 100;
    /** Не кошик (адмінка) або вже вирішено інакше; NULL теж означає адмінку. */
    private const array NOT_CART = ['admin', 'external', PriceDecision::FREE];

    public function __construct(private readonly EntityFactory $entityFactory)
    {
    }

    public static function wasPromised(object $order, object $snapshot, ?object $delivery, bool $hasWaybill): bool
    {
        $source = $snapshot->price_source ?? null;
        if ($hasWaybill || !empty($order->closed) || $delivery === null || $source === null || in_array($source, self::NOT_CART, true)) {
            return false;
        }
        $deliveryPrice = max(0.0, (float)($order->delivery_price ?? 0));
        $goods = (float)($order->total_price ?? 0) - (empty($order->separate_delivery) ? $deliveryPrice : 0.0);

        return FreeDelivery::applies($delivery, $goods) || (empty($delivery->paid) && $deliveryPrice <= 0.0);
    }

    /** @return int скільки знімків позначено */
    public function run(): int
    {
        $snapshots = $this->entityFactory->get(OrderDeliveryEntity::class);
        $deliveries = [];
        foreach ($this->entityFactory->get(DeliveriesEntity::class)->find() as $delivery) {
            $deliveries[(int)$delivery->id] = $delivery;
        }
        $marked = 0;
        for ($page = 1; ; $page++) {
            $rows = $snapshots->cols(['id', 'order_id', 'delivery_id', 'price_source'])->find(['limit' => self::BATCH, 'page' => $page]);
            if (!$rows) {
                return $marked;
            }
            $orderIds = array_map(static fn(object $row): int => (int)$row->order_id, $rows);
            $orders = [];
            foreach ($this->entityFactory->get(OrdersEntity::class)->cols(['id', 'total_price', 'delivery_price', 'separate_delivery', 'closed'])->find(['id' => $orderIds, 'limit' => self::BATCH]) as $order) {
                $orders[(int)$order->id] = $order;
            }
            $withWaybill = [];
            foreach ($this->entityFactory->get(WaybillEntity::class)->cols(['order_id', 'number'])->find(['order_id' => $orderIds, 'limit' => self::BATCH]) as $waybill) {
                if ((string)($waybill->number ?? '') !== '') {
                    $withWaybill[(int)$waybill->order_id] = true;
                }
            }
            foreach ($rows as $row) {
                $order = $orders[(int)$row->order_id] ?? null;
                if ($order !== null && self::wasPromised($order, $row, $deliveries[(int)$row->delivery_id] ?? null, isset($withWaybill[(int)$row->order_id]))) {
                    $snapshots->update((int)$row->id, ['price_source' => PriceDecision::FREE]);
                    $marked++;
                }
            }
        }
    }
}
