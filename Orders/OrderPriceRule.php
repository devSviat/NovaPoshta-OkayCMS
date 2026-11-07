<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

/**
 * What happens to an order's delivery price after the manager changes its point or address
 * (author's decision 07.11.2026): a price paid apart follows the tariff, a price inside the
 * order total is only offered, a paid order is never repriced.
 */
final class OrderPriceRule
{
    /** @return array{action: 'keep'|'free'|'show'|'auto'|'offer', price: ?float} */
    public static function decide(object $order, object $delivery, ?float $tariff, bool $unpriced): array
    {
        if ($unpriced || $tariff === null) {
            return ['action' => 'keep', 'price' => null];
        }
        $total = (float)($order->total_price ?? 0);
        $goods = empty($order->separate_delivery) ? $total - (float)($order->delivery_price ?? 0) : $total;
        $freeFrom = (float)($delivery->free_from ?? 0);
        if ($freeFrom > 0 && $goods >= $freeFrom) {
            return ['action' => 'free', 'price' => 0.0];
        }
        if (round($tariff, 2) === round((float)($order->delivery_price ?? 0), 2)) {
            return ['action' => 'keep', 'price' => $tariff];
        }
        if (!empty($order->paid)) {
            return ['action' => 'show', 'price' => $tariff];
        }
        if (!empty($order->separate_delivery)) {
            return ['action' => 'auto', 'price' => $tariff];
        }

        return ['action' => 'offer', 'price' => $tariff];
    }
}
