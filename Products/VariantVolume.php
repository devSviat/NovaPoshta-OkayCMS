<?php

namespace Okay\Modules\Sviat\NovaPoshta\Products;

/** Packed volume of a product variant, m³ — own field on the core variants table (§9). */
final class VariantVolume
{
    public const string FIELD = 'sviat__novaposhta__volume';

    /** What the manager typed: "0,5" is accepted, anything that is not a positive number becomes 0. */
    public static function clean(mixed $raw): float
    {
        $text = is_scalar($raw) ? str_replace(',', '.', trim((string)$raw)) : '';

        return is_numeric($text) && (float)$text > 0 ? round((float)$text, 5) : 0.0;
    }
}
