<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** Номер накладної Нової Пошти, як його вводять люди: пробіли й дефіси прибираються, 14 цифр лишаються. */
final class WaybillNumber
{
    public static function normalize(string $input): ?string
    {
        if (preg_match('/[^\d\s\-]/u', $input)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $input);

        return strlen($digits) === 14 ? $digits : null;
    }

    /** Публічна сторінка трекінгу; сайт приймає номер у шляху. */
    public static function trackingUrl(string $number): string
    {
        return 'https://novaposhta.ua/tracking/' . rawurlencode($number) . '/';
    }
}
