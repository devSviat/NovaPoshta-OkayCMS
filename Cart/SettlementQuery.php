<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows\NameNormalizer;

/** Що покупець ввів у поле міста, у тій формі, в якій зберігається `search_text`. */
final readonly class SettlementQuery
{
    private const int MAX_WORDS = 5;
    private const int MAX_LENGTH = 64;
    /** Сам лише тип населеного пункту ("місто") збігається з кожним населеним пунктом і нічого корисного не знаходить. */
    private const array TYPE_WORDS = ['місто', 'село', 'селище', 'смт'];

    /** @param list<string> $words уже екрановані для LIKE */
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
