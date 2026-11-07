<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** A Nova Poshta waybill number as people type it: spaces and dashes go, 14 digits stay. */
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
}
