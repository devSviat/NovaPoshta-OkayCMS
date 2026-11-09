<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows;

/**
 * Підганяє значення Нової Пошти під колонки таблиці. MySQL у strict mode відхиляє весь
 * пакет із 500 рядків через одне задовге чи позадіапазонне значення, і кожен наступний запуск
 * падав би на тому самому записі; обрізана назва чи невідомий ліміт — менше зло.
 */
final class Fit
{
    public static function text(mixed $value, int $length): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $value = (string)$value;

        return $value === '' ? null : mb_substr($value, 0, $length);
    }

    /** Поза діапазоном колонки означає, що вихідне значення — сміття: невідоме, а не обрізане. */
    public static function decimal(mixed $value, float $max): ?float
    {
        return is_numeric($value) && abs((float)$value) <= $max ? (float)$value : null;
    }

    public static function integer(mixed $value): ?int
    {
        return is_numeric($value) && abs((float)$value) <= 2147483647 ? (int)$value : null;
    }
}
