<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows;

/**
 * Fits Nova Poshta values to the table columns. MySQL in strict mode rejects a whole
 * 500-row batch for one over-long or out-of-range value, and every following run would
 * fail on the same record; a cut name or an unknown limit is the lesser harm.
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

    /** Out of the column's range means the source value is garbage: unknown, not clipped. */
    public static function decimal(mixed $value, float $max): ?float
    {
        return is_numeric($value) && abs((float)$value) <= $max ? (float)$value : null;
    }

    public static function integer(mixed $value): ?int
    {
        return is_numeric($value) && abs((float)$value) <= 2147483647 ? (int)$value : null;
    }
}
