<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

/** The order's saved choice as two lines of text, for the order page and both order emails. */
final class OrderDeliveryView
{
    /** @return array{city:string, point:string} */
    public static function lines(object $row): array
    {
        $city = array_filter([$row->settlement_name ?? null, $row->district_name ?? null, $row->area_name ?? null], static fn($part): bool => (string)$part !== '');
        $point = array_filter([$row->division_name ?? null, $row->division_address ?? null], static fn($part): bool => (string)$part !== '');

        return ['city' => implode(', ', $city), 'point' => implode(': ', $point)];
    }
}
