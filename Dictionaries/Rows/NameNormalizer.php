<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows;

/** One form of a place name for search: what a customer types and what the dictionary says must meet. */
final class NameNormalizer
{
    private const string TYPE_PREFIX = '/^(?:селище міського типу|місто|село|селище|смт)\s+/u';

    public static function normalize(string $name): string
    {
        $name = mb_strtolower($name, 'UTF-8');
        $name = str_replace(['’', 'ʼ', '`', '‘', 'ʹ'], "'", $name);
        $name = str_replace('ё', 'е', $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return preg_replace(self::TYPE_PREFIX, '', $name) ?? $name;
    }

    /** @param list<string> $alternativeNames */
    public static function searchText(string $name, array $alternativeNames): string
    {
        $variants = [];
        foreach ([$name, ...$alternativeNames] as $variant) {
            $normalized = self::normalize((string)$variant);
            if ($normalized !== '') {
                $variants[$normalized] = true;
            }
        }

        return implode(' ', array_keys($variants));
    }
}
