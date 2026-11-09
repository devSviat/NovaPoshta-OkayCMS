<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyOrders;
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

    /** Довжини колонок таблиці замовлень: значення зі старих модулів обрізаються під них, а не відхиляються. */
    private const array LEGACY_COLUMNS = [
        'mode' => 16, 'settlement_id' => null, 'settlement_name' => 255, 'district_name' => 255, 'area_name' => 255,
        'division_id' => null, 'division_number' => 32, 'division_category' => 32, 'division_name' => 255, 'division_address' => 255,
        'delivery_price' => null, 'price_source' => 16, 'cod' => null, 'street_name' => 100, 'house' => 10, 'flat' => 10,
        'floor' => null, 'elevator' => null, 'address_note' => 40, 'lifting' => null, 'legacy' => 16,
    ];

    /** @return array<string, mixed> лише колонки, які може задати перенесений рядок; текст обрізано під свою колонку */
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
     * Рядок, перенесений з NovaposhtaCost. Ніколи не замінює те, що в модулі вже є.
     * @return bool false, коли замовлення вже має рядок
     */
    /** @return LegacyOrders::DONE|LegacyOrders::EXISTED|LegacyOrders::FAILED */
    public function importLegacy(int $orderId, int $deliveryId, array $values): string
    {
        $entity = $this->entityFactory->get(OrderDeliveryEntity::class);
        if ($entity->findOne(['order_id' => $orderId])) {
            return LegacyOrders::EXISTED;
        }
        $now = date('Y-m-d H:i:s');
        // Ядро ковтає помилку SQL і повертає false: без перевірки перенесення рахувало б незаписане як перенесене.
        $added = $entity->add(['order_id' => $orderId, 'delivery_id' => $deliveryId, 'created_at' => $now, 'updated_at' => $now] + self::legacyValues($values));

        return $added ? LegacyOrders::DONE : LegacyOrders::FAILED;
    }
}
