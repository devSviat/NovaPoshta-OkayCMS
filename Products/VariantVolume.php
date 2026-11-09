<?php

namespace Okay\Modules\Sviat\NovaPoshta\Products;

/** Об'єм варіанта товару в упаковці, m³ — власне поле в таблиці варіантів ядра. */
final class VariantVolume
{
    public const string FIELD = 'sviat__novaposhta__volume';

    /** Що ввів менеджер: "0,5" приймається, усе, що не є додатним числом, стає 0. */
    public static function clean(mixed $raw): float
    {
        $text = is_scalar($raw) ? str_replace(',', '.', trim((string)$raw)) : '';

        return is_numeric($text) && (float)$text > 0 ? round((float)$text, 5) : 0.0;
    }
}
