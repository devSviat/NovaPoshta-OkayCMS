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

    /**
     * Назви копіюються українською: замовлення зберігає те, що обрали, навіть якщо довідник зміниться.
     *
     * @param array{delivery_price: mixed, price_source: ?string, delivery_term: ?string, cod: int, cod_fee: ?float}|null $pricing
     */
    public function save(int $orderId, CartSelection $selection, ?array $pricing = null): void
    {
        if (!$selection->applies()) {
            return;
        }
        $address = $selection->address;
        $now = date('Y-m-d H:i:s');
        $entity = $this->entityFactory->get(OrderDeliveryEntity::class);
        $existing = $entity->findOne(['order_id' => $orderId]);
        $row = [
            'order_id' => $orderId,
            'delivery_id' => (int)$selection->delivery->id,
            'mode' => ($address === null ? DeliveryMode::Points : DeliveryMode::Address)->value,
            'settlement_id' => (int)$selection->settlement->id,
            'settlement_name' => (string)$selection->settlement->name_uk,
            'district_name' => $selection->settlement->district_uk ?: null,
            'area_name' => $selection->settlement->area_uk ?: null,
            'updated_at' => $now,
        ];
        if ($address === null) {
            [$title, $place] = array_pad(explode(': ', (string)$selection->division->name_uk, 2), 2, null);
            $row += [
                'division_id' => (int)$selection->division->id,
                'division_number' => (string)$selection->division->number,
                'division_category' => (string)$selection->division->category,
                'division_name' => $title,
                'division_address' => $place ?? ($selection->division->address_uk ?: null),
            ];
            $row += array_fill_keys(['street_name', 'street_ref', 'house', 'flat', 'entrance', 'floor', 'address_note'], null) + ['elevator' => 0, 'lifting' => 0];
        } else {
            $row += [
                'division_id' => null,
                'division_number' => null,
                'division_category' => null,
                'division_name' => null,
                'division_address' => null,
                'street_name' => $address->street,
                'street_ref' => $address->streetRef,
                'house' => $address->house,
                'flat' => $address->flat,
                'entrance' => $address->entrance,
                'floor' => $address->floor,
                'elevator' => $address->elevator ? 1 : 0,
                'address_note' => $address->note,
                'lifting' => $address->lifting ? 1 : 0,
            ];
        }
        $row = ($pricing ?? []) + $row;
        if ($existing) {
            $row['legacy'] = self::legacyAfterChoice($existing->legacy ?? null);
        }
        $existing ? $entity->update((int)$existing->id, $row) : $entity->add($row + ['created_at' => $now]);
    }

    /** Ціна доставки, яку менеджер задав в адмінці. */
    public function setPrice(int $orderId, float $price, string $source): void
    {
        $entity = $this->entityFactory->get(OrderDeliveryEntity::class);
        $row = $entity->findOne(['order_id' => $orderId]);
        if (is_object($row)) {
            $entity->update((int)$row->id, ['delivery_price' => $price, 'price_source' => $source, 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    public function forOrder(int $orderId): ?object
    {
        return $this->entityFactory->get(OrderDeliveryEntity::class)->findOne(['order_id' => $orderId]) ?: null;
    }

    /** Останній вибір покупця, який знову пропонується в кошику. */
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

    /** Пункт видачі чи адресу знову обрано з довідника: перенесений рядок тепер зіставлено. */
    public static function legacyAfterChoice(?string $was): ?string
    {
        return $was === null ? null : 'migrated';
    }
}
