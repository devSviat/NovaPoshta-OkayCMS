<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/** A point's name without what the cart already shows: the weight limit has its own label, and the whole block is Nova Poshta's. */
final class PointTitle
{
    public static function short(string $title): string
    {
        $title = preg_replace('/\s*\((?:до|up to)\s[^)]*\)/u', '', $title) ?? $title;
        $title = preg_replace('/^(Поштомат|Parcel Locker)\s+"(?:Нова Пошта|Nova Poshta)"\s+/u', '$1 ', $title) ?? $title;

        return trim($title);
    }
}
