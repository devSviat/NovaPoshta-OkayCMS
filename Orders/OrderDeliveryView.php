<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Waybills\PendingWaybill;
use Okay\Modules\Sviat\NovaPoshta\Cart\PointTitle;

/** The order's saved choice as lines of text, for the order pages (customer and admin) and both order emails. */
final class OrderDeliveryView
{
    /**
     * @param ?float $orderDeliveryPrice the order's delivery price now: a manager may have set it after checkout
     * @param bool $codNow the order's payment is cash on delivery now (the customer may change it on the order page)
     * @param bool $pricePublic the delivery method shows its price to customers; the shop sees the commission anyway
     * @return array{city:string, point:string, term:?string, priceUnconfirmed:bool, address:?array, lifting:bool, codFee:?float, codFeePublic:bool}
     */
    public static function lines(object $row, ?float $orderDeliveryPrice = null, bool $codNow = false, bool $pricePublic = true): array
    {
        $city = array_filter([$row->settlement_name ?? null, $row->district_name ?? null, $row->area_name ?? null], static fn($part): bool => (string)$part !== '');
        $name = PointTitle::short((string)($row->division_name ?? ''));
        // A branch name carries its street after a colon; a parcel locker's does not, so it gets the address.
        $point = array_filter([$name, str_contains($name, ':') ? null : ($row->division_address ?? null)], static fn($part): bool => (string)$part !== '');

        $term = (string)($row->delivery_term ?? '');

        return [
            'city' => implode(', ', $city),
            'point' => implode(': ', $point),
            'term' => preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: 00:00:00)?$/', $term, $m) ? "{$m[3]}.{$m[2]}.{$m[1]}" : null,
            // Nova Poshta gave no price at checkout: no delivery is charged, the manager states it.
            'priceUnconfirmed' => ($row->price_source ?? null) === 'fallback' && !($orderDeliveryPrice > 0),
            'legacyUnmatched' => ($row->legacy ?? null) === 'unmatched',
            'address' => ($row->mode ?? null) === 'address' ? self::address($row) : null,
            'lifting' => !empty($row->lifting),
            // Paid by the recipient at the branch, not part of the order total.
            'codFee' => $codNow && (float)($row->cod_fee ?? 0) > 0 ? round((float)$row->cod_fee, 2) : null,
            'codFeePublic' => $pricePublic,
        ];
    }

    /** Address parts; templates put their labels in the page's language. */
    private static function address(object $row): array
    {
        $text = static fn(string $name): ?string => trim((string)($row->$name ?? '')) === '' ? null : trim((string)$row->$name);

        return [
            'street' => $text('street_name'),
            'streetRef' => $text('street_ref'),
            'house' => $text('house'),
            'flat' => $text('flat'),
            'entrance' => $text('entrance'),
            'floor' => (int)($row->floor ?? 0) > 0 ? (int)$row->floor : null,
            'elevator' => !empty($row->elevator),
            'note' => $text('address_note'),
        ];
    }

    /**
     * The tracking link the customer sees; none for a waybill being made or deleted at NP.
     *
     * @return ?array{number: string, numberSpaced: string, url: string}
     */
    public static function waybill(?object $row): ?array
    {
        if ($row === null || PendingWaybill::is($row) || (int)($row->status_code ?? 0) === 2 || strlen((string)$row->number) !== 14) {
            return null;
        }
        $number = (string)$row->number;

        return [
            'number' => $number,
            'numberSpaced' => substr($number, 0, 2) . ' ' . implode(' ', str_split(substr($number, 2), 4)),
            'url' => \Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillNumber::trackingUrl($number),
        ];
    }
}
