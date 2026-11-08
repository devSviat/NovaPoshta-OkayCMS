<?php

namespace Okay\Modules\Sviat\NovaPoshta\Integration;

use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity;

/**
 * Read-only contract for other modules (Checkbox, Messaging, OrdersExport, LiqPay). Everything
 * outside Integration\ may change without notice; this class and its value objects do not.
 *
 * Use it only when the module is active: class_exists(NpOrders::class) and
 * Modules::isActiveModule('Sviat', 'NovaPoshta'); a disabled module registers no services.
 */
final class NpOrders
{
    private const int RECEIVED = 9;
    private const array ARRIVED = [7, 8];

    public function __construct(private readonly EntityFactory $entityFactory)
    {
    }

    /**
     * @param list<int> $orderIds
     * @return array<int, NpShipment> by order id; orders without a waybill number are left out
     */
    public function shipments(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if ($orderIds === []) {
            return [];
        }

        return $this->build($this->entityFactory->get(WaybillEntity::class)->noLimit()->find(['order_id' => $orderIds]));
    }

    /** @return array<int, NpShipment> waybills Nova Poshta still moves (not final, number known) */
    public function unfinished(): array
    {
        return $this->build($this->entityFactory->get(WaybillEntity::class)->noLimit()->find(['due' => 1]));
    }

    /**
     * Orders whose parcel was received on or after $since, by the date of the status at Nova Poshta.
     * The row's own update time is not used: a migration or a refresh rewrites it for old parcels.
     *
     * @return list<int>
     */
    public function receivedSince(string $since): array
    {
        return self::receivedFrom($this->entityFactory->get(WaybillEntity::class)->noLimit()->find(['status_codes' => [self::RECEIVED]]), $since);
    }

    public function addressOf(int $orderId): ?NpAddress
    {
        return self::address($this->entityFactory->get(OrderDeliveryEntity::class)->findOne(['order_id' => $orderId]) ?: null);
    }

    public static function waybillTable(): string
    {
        return WaybillEntity::getTable();
    }

    /** @param ?object $delivery the order's snapshot row: its `cod` decides for waybills that keep no services */
    public static function shipment(object $waybill, ?object $delivery): ?NpShipment
    {
        $number = trim((string)($waybill->number ?? ''));
        if ($number === '' || ($waybill->api ?? '') === 'pending') {
            return null;
        }
        $code = is_numeric($waybill->status_code ?? null) ? (int)$waybill->status_code : null;
        $statusAt = self::date($waybill->status_at ?? null);
        $services = is_string($waybill->services ?? null) ? json_decode($waybill->services, true) : null;
        $cod = is_array($services) && array_key_exists('cod', $services) ? (bool)$services['cod'] : !empty($delivery->cod ?? null);

        return new NpShipment((int)$waybill->order_id, $number, $code, $statusAt, in_array($code, self::ARRIVED, true) ? $statusAt : null, $cod);
    }

    /** The date a received parcel counts from: its status date, else when it was last tracked; null if not received. */
    public static function receivedAt(object $waybill): ?string
    {
        if (trim((string)($waybill->number ?? '')) === '' || (int)($waybill->status_code ?? 0) !== self::RECEIVED) {
            return null;
        }

        return self::date($waybill->status_at ?? null) ?? self::date($waybill->tracked_at ?? null);
    }

    /**
     * @param iterable<object> $waybills
     * @return list<int>
     */
    public static function receivedFrom(iterable $waybills, string $since): array
    {
        $ids = [];
        foreach ($waybills as $waybill) {
            $at = self::receivedAt($waybill);
            if ($at !== null && $at >= $since) {
                $ids[] = (int)$waybill->order_id;
            }
        }

        return array_values(array_unique($ids));
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
        $deliveries = $orderIds === [] ? [] : $this->entityFactory->get(OrderDeliveryEntity::class)->mappedBy('order_id')->noLimit()->find(['order_id' => $orderIds]);
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
