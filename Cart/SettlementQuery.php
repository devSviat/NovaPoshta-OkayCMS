<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows\NameNormalizer;

/** What a customer typed in the city field, in the form `search_text` is stored in. */
final readonly class SettlementQuery
{
    private const int MAX_WORDS = 5;
    private const int MAX_LENGTH = 64;
    /** A settlement type alone ("місто") matches every settlement and finds nothing useful. */
    private const array TYPE_WORDS = ['місто', 'село', 'селище', 'смт'];

    /** @param list<string> $words already escaped for LIKE */
    private function __construct(public array $words, public string $prefix)
    {
    }

    public static function parse(string $raw): ?self
    {
        $raw = preg_replace('/\p{Cf}+/u', '', $raw) ?? '';
        $normalized = mb_substr(NameNormalizer::normalize($raw), 0, self::MAX_LENGTH);
        $words = array_values(array_filter(
            explode(' ', $normalized),
            static fn(string $word): bool => $word !== '' && !in_array($word, self::TYPE_WORDS, true),
        ));
        if ($words === []) {
            return null;
        }
        $words = array_map(static fn(string $w): string => addcslashes($w, '\\%_'), array_slice($words, 0, self::MAX_WORDS));

        return new self($words, $words[0]);
    }
}
