<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyOrders;

final class FakeLegacyOrders implements LegacyOrders
{
    /** @var array<int, object> id => (id, delivery_id, delivery_price, payment_method_id) */
    public array $orders = [];
    /** @var array<int, array> id замовлення => імпортовані значення */
    public array $rows = [];
    /** @var array<int, array> id замовлення => імпортована накладна */
    public array $waybills = [];
    /** Імітує збій запису в базу. */
    public bool $failWrites = false;

    public function order(int $orderId): ?object
    {
        return $this->orders[$orderId] ?? null;
    }

    public function hasDelivery(int $orderId): bool
    {
        return isset($this->rows[$orderId]);
    }

    public function importDelivery(int $orderId, int $deliveryId, array $values): string
    {
        if (isset($this->rows[$orderId])) {
            return LegacyOrders::EXISTED;
        }
        if ($this->failWrites) {
            return LegacyOrders::FAILED;
        }
        $this->rows[$orderId] = $values + ['delivery_id' => $deliveryId];

        return LegacyOrders::DONE;
    }

    public function hasWaybill(int $orderId): bool
    {
        return isset($this->waybills[$orderId]);
    }

    public function waybillOwner(string $number): ?int
    {
        foreach ($this->waybills as $orderId => $values) {
            if (($values['number'] ?? null) === $number) {
                return $orderId;
            }
        }

        return null;
    }

    public function importWaybill(int $orderId, array $values): string
    {
        if (isset($this->waybills[$orderId])) {
            return LegacyOrders::EXISTED;
        }
        if ($this->failWrites) {
            return LegacyOrders::FAILED;
        }
        $this->waybills[$orderId] = $values;

        return LegacyOrders::DONE;
    }
}
