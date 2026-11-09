<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/**
 * Назва пункту видачі без того, що кошик уже показує: ліміт ваги має власну мітку,
 * а весь блок і так належить Новій Пошті.
 */
final class PointTitle
{
    public static function short(string $title): string
    {
        $title = preg_replace('/\s*\((?:до|up to)\s[^)]*\)/u', '', $title) ?? $title;
        $title = preg_replace('/^(Поштомат|Parcel Locker)\s+"(?:Нова Пошта|Nova Poshta)"\s+/u', '$1 ', $title) ?? $title;

        return trim($title);
    }
}
