<?php

namespace Okay\Modules\Sviat\NovaPoshta\Integration;

use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity;

/**
 * Контракт лише на читання для інших модулів (Checkbox, Messaging, OrdersExport, LiqPay). Усе поза
 * Integration\ може змінитися без попередження; цей клас і його value objects — ні.
 *
 * Використовуйте його лише тоді, коли модуль активний: class_exists(NpOrders::class) і
 * Modules::isActiveModule('Sviat', 'NovaPoshta'); вимкнений модуль не реєструє сервісів.
 */
final class NpOrders
{
    private const array ARRIVED = [7, 8];
    private const array WAYBILL_COLUMNS = ['order_id', 'number', 'status_code', 'status_at', 'services'];

    public function __construct(private readonly EntityFactory $entityFactory)
    {
    }

    /**
     * @param list<int> $orderIds
     * @return array<int, NpShipment> за id замовлення; замовлення без номера накладної пропущено
     */
    public function shipments(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map(intval(...), $orderIds))));
        if ($orderIds === []) {
            return [];
        }

        return $this->build($this->entityFactory->get(WaybillEntity::class)->noLimit()->cols(self::WAYBILL_COLUMNS)->find(['order_id' => $orderIds]));
    }

    /** @return array<int, NpShipment> накладні, які Нова Пошта ще везе (не фінальні, номер відомий) */
    public function unfinished(): array
    {
        return $this->build($this->entityFactory->get(WaybillEntity::class)->noLimit()->cols(self::WAYBILL_COLUMNS)->find(['due' => 1]));
    }

    /**
     * Замовлення, чию посилку отримано $since або пізніше, за датою статусу в Новій Пошті.
     * Власний час оновлення рядка не використовується: перенесення чи оновлення переписує його для старих посилок.
     *
     * @return list<int>
     */
    public function receivedSince(string $since): array
    {
        $ids = $this->entityFactory->get(WaybillEntity::class)->noLimit()->cols(['order_id'])->find(['received_since' => $since]);

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    public function addressOf(int $orderId): ?NpAddress
    {
        return self::address($this->entityFactory->get(OrderDeliveryEntity::class)->findOne(['order_id' => $orderId]) ?: null);
    }

    public static function waybillTable(): string
    {
        return WaybillEntity::getTable();
    }

    /** @param ?object $delivery рядок знімка доставки замовлення: його `cod` вирішує для накладних, що не зберігають послуг */
    public static function shipment(object $waybill, ?object $delivery): ?NpShipment
    {
        $number = trim((string)($waybill->number ?? ''));
        if ($number === '') {
            return null;
        }
        $code = is_numeric($waybill->status_code ?? null) ? (int)$waybill->status_code : null;
        $statusAt = self::date($waybill->status_at ?? null);
        $services = is_string($waybill->services ?? null) ? json_decode($waybill->services, true) : null;
        $cod = is_array($services) && array_key_exists('cod', $services) ? (bool)$services['cod'] : !empty($delivery->cod ?? null);

        return new NpShipment((int)$waybill->order_id, $number, $code, $statusAt, in_array($code, self::ARRIVED, true) ? $statusAt : null, $cod);
    }

    public static function address(?object $delivery): ?NpAddress
    {
        if ($delivery === null) {
            return null;
        }
        $text = static fn(string $name): string => trim((string)($delivery->$name ?? ''));
        if (($delivery->mode ?? null) === 'address') {
            $parts = [$text('street_name'), $text('house'), $text('flat') !== '' ? 'кв. ' . $text('flat') : ''];
        } else {
            $parts = [$text('division_name'), $text('division_address')];
        }

        return new NpAddress($text('settlement_name'), implode(', ', array_filter($parts, static fn(string $p): bool => $p !== '')));
    }

    /** @param iterable<object> $waybills */
    private function build(iterable $waybills): array
    {
        $waybills = is_array($waybills) ? $waybills : iterator_to_array($waybills);
        $orderIds = array_map(static fn(object $w): int => (int)$w->order_id, $waybills);
        $deliveries = $orderIds === [] ? [] : $this->entityFactory->get(OrderDeliveryEntity::class)->mappedBy('order_id')->noLimit()->cols(['order_id', 'cod'])->find(['order_id' => $orderIds]);
        $result = [];
        foreach ($waybills as $waybill) {
            $shipment = self::shipment($waybill, $deliveries[(int)$waybill->order_id] ?? null);
            if ($shipment !== null) {
                $result[$shipment->orderId] = $shipment;
            }
        }

        return $result;
    }

    private static function date(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' || str_starts_with($value, '0000') ? null : $value;
    }
}
