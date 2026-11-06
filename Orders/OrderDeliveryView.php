<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

/** The order's saved choice as lines of text, for the order pages (customer and admin) and both order emails. */
final class OrderDeliveryView
{
    /** @return array{city:string, point:string, term:?string, priceUnconfirmed:bool, address:?array, lifting:bool} */
    /** @param ?float $orderDeliveryPrice the order's delivery price now: a manager may have set it after checkout */
    public static function lines(object $row, ?float $orderDeliveryPrice = null): array
    {
        $city = array_filter([$row->settlement_name ?? null, $row->district_name ?? null, $row->area_name ?? null], static fn($part): bool => (string)$part !== '');
        $point = array_filter([$row->division_name ?? null, $row->division_address ?? null], static fn($part): bool => (string)$part !== '');

        $term = (string)($row->delivery_term ?? '');

        return [
            'city' => implode(', ', $city),
            'point' => implode(': ', $point),
            'term' => preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $term, $m) ? "{$m[3]}.{$m[2]}.{$m[1]}" : null,
            // Nova Poshta gave no price at checkout: no delivery is charged, the manager states it.
            'priceUnconfirmed' => ($row->price_source ?? null) === 'fallback' && !($orderDeliveryPrice > 0),
            'address' => ($row->mode ?? null) === 'address' ? self::address($row) : null,
            'lifting' => !empty($row->lifting),
        ];
    }

    /** Address parts; templates put their labels in the page's language. */
    private static function address(object $row): array
    {
        $text = static fn(string $name): ?string => trim((string)($row->$name ?? '')) === '' ? null : trim((string)$row->$name);

        return [
            'street' => $text('street_name'),
            'house' => $text('house'),
            'flat' => $text('flat'),
            'entrance' => $text('entrance'),
            'floor' => (int)($row->floor ?? 0) > 0 ? (int)$row->floor : null,
            'elevator' => !empty($row->elevator),
            'note' => $text('address_note'),
        ];
    }
}
