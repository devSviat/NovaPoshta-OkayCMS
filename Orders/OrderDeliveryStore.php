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
     * Names are copied in Ukrainian: the order keeps what was chosen even if the dictionary changes.
     *
     * @param array{delivery_price: mixed, price_source: ?string, delivery_term: ?string, cod: int, cod_fee: ?float}|null $pricing
     */
    public function save(int $orderId, CartSelection $selection, ?array $pricing = null): void
    {
        if (!$selection->applies()) {
            return;
        }
        $address = $selection->address;
        if ($address !== null && !self::stored('street_name')) {
            return;
        }
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
            if (self::stored('street_name')) {
                $row += array_fill_keys(['street_name', 'street_ref', 'house', 'flat', 'entrance', 'floor', 'address_note'], null) + ['elevator' => 0, 'lifting' => 0];
            }
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
        $pricing = self::stored('delivery_price') ? ($pricing ?? []) : [];
        if (!self::stored('cod_fee')) {
            unset($pricing['cod_fee']);
        }
        $row = $pricing + $row;
        $existing ? $entity->update((int)$existing->id, $row) : $entity->add($row + ['created_at' => $now]);
    }

    /** Columns added after 0.3.0 are registered by Init only once their update has created them. */
    private static function stored(string $column): bool
    {
        return in_array($column, OrderDeliveryEntity::getFields(), true);
    }

    /** The delivery price the manager set from the admin (stage 4a2). */
    public function setPrice(int $orderId, float $price, string $source): void
    {
        if (!self::stored('delivery_price')) {
            return;
        }
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

    /** Column lengths of the order table: values from old modules are cut to fit, never rejected. */
    private const array LEGACY_COLUMNS = [
        'mode' => 16, 'settlement_id' => null, 'settlement_name' => 255, 'district_name' => 255, 'area_name' => 255,
        'division_id' => null, 'division_number' => 32, 'division_category' => 32, 'division_name' => 255, 'division_address' => 255,
        'delivery_price' => null, 'price_source' => 16, 'cod' => null, 'street_name' => 100, 'house' => 10, 'flat' => 10,
        'floor' => null, 'elevator' => null, 'address_note' => 40, 'lifting' => null, 'legacy' => 16,
    ];

    /** @return array<string, mixed> only the columns a migrated row may set, strings cut to their column */
    public static function legacyValues(array $values): array
    {
        $row = [];
        foreach (self::LEGACY_COLUMNS as $column => $max) {
            if (!array_key_exists($column, $values)) {
                continue;
            }
            $value = $values[$column];
            $row[$column] = $max !== null && is_string($value) ? mb_substr($value, 0, $max) : $value;
        }

        return $row;
    }

    /**
     * A row carried over from NovaposhtaCost (stage 5). Never replaces what the module already has.
     * @return bool false when the order already has a row
     */
    public function importLegacy(int $orderId, int $deliveryId, array $values): bool
    {
        $entity = $this->entityFactory->get(OrderDeliveryEntity::class);
        if ($entity->findOne(['order_id' => $orderId])) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $row = array_filter(self::legacyValues($values), static fn(string $column): bool => self::stored($column), ARRAY_FILTER_USE_KEY);
        $entity->add(['order_id' => $orderId, 'delivery_id' => $deliveryId, 'created_at' => $now, 'updated_at' => $now] + $row);

        return true;
    }
}
