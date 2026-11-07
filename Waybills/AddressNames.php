<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/**
 * Street names reduced to what can be compared with Nova Poshta's text.
 * Ported from Sviat/NovaPoshtaTracking Helpers/AddressNames (street part only).
 */
final class AddressNames
{
    /** Street type => its spellings (compared lowercase). */
    private const array STREET_TYPES = [
        'вул' => ['вул.', 'вул', 'вулиця'],
        'просп' => ['просп.', 'просп', 'пр-т', 'проспект'],
        'пров' => ['пров.', 'пров', 'провулок'],
        'бульв' => ['бульв.', 'бул.', 'бульвар'],
        'пл' => ['пл.', 'площа'],
        'наб' => ['наб.', 'набережна'],
        'узв' => ['узв.', 'узвіз'],
        'туп' => ['туп.', 'тупик'],
        'шосе' => ['шосе', 'ш.'],
        'алея' => ['алея'],
        'майдан' => ['майдан', 'майд.'],
        'проїзд' => ['проїзд'],
    ];

    /** "вул. Городоцька" → "городоцька". */
    public static function streetCore(string $street): string
    {
        [$type, $rest] = self::splitType(self::normalize($street));

        return $type === null ? self::normalize($street) : $rest;
    }

    /** The canonical type (`вул`, `просп`…) of a street name or of a bare type; null when none is given. */
    public static function streetType(string $street): ?string
    {
        $value = self::normalize($street);
        foreach (self::STREET_TYPES as $type => $spellings) {
            if (in_array($value, $spellings, true)) {
                return $type;
            }
        }

        return self::splitType($value)[0];
    }

    public static function normalize(string $value): string
    {
        $value = str_replace(['’', 'ʼ', '`', '´'], "'", $value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($value, 'UTF-8');
    }

    /** @return array{0: ?string, 1: string} */
    private static function splitType(string $value): array
    {
        foreach (self::STREET_TYPES as $type => $spellings) {
            foreach ($spellings as $spelling) {
                $quoted = preg_quote($spelling, '/');
                // "пл." is a type, "Плиткова" is a name: a spelling without a dot needs a word boundary.
                $boundary = str_ends_with($spelling, '.') ? '' : '(?=\s|$)';
                if (preg_match('/^' . $quoted . $boundary . '\s*(.+)$/u', $value, $m)
                    || preg_match('/^(.+?)\s+' . $quoted . $boundary . '$/u', $value, $m)
                ) {
                    return [$type, trim($m[1])];
                }
            }
        }

        return [null, $value];
    }
}
