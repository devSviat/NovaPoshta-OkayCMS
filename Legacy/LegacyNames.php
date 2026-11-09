<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Назви старих довідників і наші, зведені до однієї форми перед порівнянням. */
final class LegacyNames
{
    private const string TYPE = '/^(?:селище міського типу|місто|село|селище|смт|м\.|с\.)\s+/u';
    private const string STREET_WORDS = '/(?<!\pL)(?:вулиця|вул|проспект|просп|провулок|пров|бульвар|бульв|площа|пл|шосе|узвіз)(?!\pL)/u';

    public static function place(string $name): string
    {
        $name = self::plain($name);
        // getCities додає район до тезок: "Іванівка (Березівський р-н)".
        $name = trim((string)preg_replace('/\s*\(.*\)\s*$/u', '', $name));

        return trim((string)preg_replace(self::TYPE, '', $name));
    }

    public static function area(string $name): string
    {
        return trim((string)preg_replace('/\s*(?:область|обл\.?)$/u', '', self::plain($name)));
    }

    public static function district(string $name): string
    {
        return trim((string)preg_replace('/\s*(?:район|р-н)$/u', '', self::plain($name)));
    }

    /** Адреса пункту видачі: те, що йде після "Відділення №13 (до 30 кг):", без позначень вулиці й розділових знаків. */
    public static function address(string $name): string
    {
        $parts = explode(':', $name, 2);
        $address = self::plain($parts[1] ?? $parts[0]);
        $address = (string)preg_replace(self::STREET_WORDS, ' ', $address);
        $address = (string)preg_replace('/[^\pL\pN\']+/u', ' ', $address);

        return trim((string)preg_replace('/\s+/u', ' ', $address));
    }

    /** "Number" пункту видачі НП: NovaposhtaCost 1.2.0 зберігає його в колонці, старіші рядки — лише в назві ("№13"). */
    public static function number(?int $number, string $name): ?int
    {
        if ($number !== null && $number > 0) {
            return $number;
        }

        return preg_match('/№\s*(\d+)/u', $name, $m) ? (int)$m[1] : null;
    }

    private static function plain(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace(['ʼ', '’', '`', '´'], "'", $name);

        return (string)preg_replace('/\s+/u', ' ', $name);
    }
}
