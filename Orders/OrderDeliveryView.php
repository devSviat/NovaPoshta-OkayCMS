<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

/** The order's saved choice as two lines of text, for the order page and both order emails. */
final class OrderDeliveryView
{
    /** @return array{city:string, point:string, term:?string, priceUnconfirmed:bool, address:?array, lifting:bool} */
    public static function lines(object $row): array
    {
        $city = array_filter([$row->settlement_name ?? null, $row->district_name ?? null, $row->area_name ?? null], static fn($part): bool => (string)$part !== '');
        $point = array_filter([$row->division_name ?? null, $row->division_address ?? null], static fn($part): bool => (string)$part !== '');

        $term = (string)($row->delivery_term ?? '');

        return [
            'city' => implode(', ', $city),
            'point' => implode(': ', $point),
            'term' => preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $term, $m) ? "{$m[3]}.{$m[2]}.{$m[1]}" : null,
            // Nova Poshta did not answer at checkout: the order has the delivery method's own price.
            'priceUnconfirmed' => ($row->price_source ?? null) === 'fallback',
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
