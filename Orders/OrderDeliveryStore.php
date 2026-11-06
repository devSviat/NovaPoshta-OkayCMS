<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Core\EntityFactory;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartSelection;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity;

final class OrderDeliveryStore
{
    public function __construct(private readonly EntityFactory $entityFactory)
    {
    }

    /** Names are copied in Ukrainian: the order keeps what was chosen even if the dictionary changes. */
    /** @param array{delivery_price: mixed, price_source: ?string, delivery_term: ?string, cod: int}|null $pricing */
    public function save(int $orderId, CartSelection $selection, ?array $pricing = null): void
    {
        if (!$selection->applies() || $selection->division === null) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        [$title, $place] = array_pad(explode(': ', (string)$selection->division->name_uk, 2), 2, null);
        $entity = $this->entityFactory->get(OrderDeliveryEntity::class);
        $existing = $entity->findOne(['order_id' => $orderId]);
        $row = [
            'order_id' => $orderId,
            'delivery_id' => (int)$selection->delivery->id,
            'mode' => DeliveryMode::Points->value,
            'settlement_id' => (int)$selection->settlement->id,
            'settlement_name' => (string)$selection->settlement->name_uk,
            'district_name' => $selection->settlement->district_uk ?: null,
            'area_name' => $selection->settlement->area_uk ?: null,
            'division_id' => (int)$selection->division->id,
            'division_number' => (string)$selection->division->number,
            'division_category' => (string)$selection->division->category,
            'division_name' => $title,
            'division_address' => $place ?? ($selection->division->address_uk ?: null),
            'updated_at' => $now,
        ] + (self::pricingStored() ? ($pricing ?? []) : []);
        $existing ? $entity->update((int)$existing->id, $row) : $entity->add($row + ['created_at' => $now]);
    }

    /** The price columns are registered by Init only once the 0.4.0 update has created them. */
    private static function pricingStored(): bool
    {
        return in_array('delivery_price', OrderDeliveryEntity::getFields(), true);
    }

    public function forOrder(int $orderId): ?object
    {
        return $this->entityFactory->get(OrderDeliveryEntity::class)->findOne(['order_id' => $orderId]) ?: null;
    }

    /** The customer's last choice, offered again in the cart. */
    public function lastForUser(int $userId): ?object
    {
        if ($userId <= 0) {
            return null;
        }
        $orderIds = array_keys($this->entityFactory->get(OrdersEntity::class)->mappedBy('id')->cols(['id'])->find(['user_id' => $userId, 'limit' => 20]));
        if ($orderIds === []) {
            return null;
        }
        $rows = $this->entityFactory->get(OrderDeliveryEntity::class)->find(['order_id' => $orderIds, 'limit' => 1]);

        return $rows ? reset($rows) : null;
    }
}
