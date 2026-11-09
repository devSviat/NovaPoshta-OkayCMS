<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Waybills\PendingWaybill;
use Okay\Modules\Sviat\NovaPoshta\Cart\PointTitle;

/** Збережений вибір замовлення як рядки тексту — для сторінок замовлення (покупця й адмінки) та обох листів замовлення. */
final class OrderDeliveryView
{
    /**
     * @param ?float $orderDeliveryPrice поточна ціна доставки замовлення: менеджер міг задати її після оформлення замовлення
     * @param bool $codNow оплата замовлення зараз — післяплата (покупець може змінити її на сторінці замовлення)
     * @param bool $pricePublic спосіб доставки показує ціну покупцям; магазин бачить комісію в будь-якому разі
     * @return array{city:string, point:string, term:?string, priceUnconfirmed:bool, address:?array, lifting:bool, codFee:?float, codFeePublic:bool}
     */
    public static function lines(object $row, ?float $orderDeliveryPrice = null, bool $codNow = false, bool $pricePublic = true): array
    {
        $city = array_filter([$row->settlement_name ?? null, $row->district_name ?? null, $row->area_name ?? null], static fn($part): bool => (string)$part !== '');
        $name = PointTitle::short((string)($row->division_name ?? ''));
        // Назва відділення містить вулицю після двокрапки; у назві поштомата її немає, тож він отримує адресу.
        $point = array_filter([$name, str_contains($name, ':') ? null : ($row->division_address ?? null)], static fn($part): bool => (string)$part !== '');

        $term = (string)($row->delivery_term ?? '');

        return [
            'city' => implode(', ', $city),
            'point' => implode(': ', $point),
            'term' => preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: 00:00:00)?$/', $term, $m) ? "{$m[3]}.{$m[2]}.{$m[1]}" : null,
            // Нова Пошта не дала ціни під час оформлення замовлення: доставку не нараховано, її вказує менеджер.
            'priceUnconfirmed' => ($row->price_source ?? null) === 'fallback' && !($orderDeliveryPrice > 0),
            'legacyUnmatched' => ($row->legacy ?? null) === 'unmatched',
            'address' => ($row->mode ?? null) === 'address' ? self::address($row) : null,
            'lifting' => !empty($row->lifting),
            // Сплачує отримувач у відділенні, не входить у суму замовлення.
            'codFee' => $codNow && (float)($row->cod_fee ?? 0) > 0 ? round((float)$row->cod_fee, 2) : null,
            'codFeePublic' => $pricePublic,
        ];
    }

    /** Частини адреси; шаблони підписують їх мовою сторінки. */
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
     * Посилання на трекінг, яке бачить покупець; його немає для накладної, яку саме створюють чи видаляють у НП.
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
