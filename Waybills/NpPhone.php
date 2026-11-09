<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** Нова Пошта приймає лише українські номери, у вигляді 380XXXXXXXXX. */
final class NpPhone
{
    public static function normalize(string $input): ?string
    {
        $digits = preg_replace('/\D/', '', $input);
        $phone = match (true) {
            strlen($digits) === 12 && str_starts_with($digits, '380') => $digits,
            strlen($digits) === 11 && str_starts_with($digits, '80') => '3' . $digits,
            strlen($digits) === 10 && str_starts_with($digits, '0') => '38' . $digits,
            default => null,
        };

        return $phone;
    }
}
