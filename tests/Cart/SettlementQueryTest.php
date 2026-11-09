<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Cart\SettlementQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SettlementQueryTest extends TestCase
{
    public static function queries(): array
    {
        return [
            'plain' => ['Київ', ['київ']],
            'apostrophe forms' => ['Кам’янське', ["кам'янське"]],
            'type prefix' => ['смт Ворзель', ['ворзель']],
            'two words' => ['  Біла   Церква ', ['біла', 'церква']],
            'latin' => ['ZAP', ['zap']],
            'like wildcards are literal' => ['50%_x', ['50\\%\\_x']],
            'six words cut to five' => ['a b c d e f', ['a', 'b', 'c', 'd', 'e']],
        ];
    }

    #[DataProvider('queries')]
    public function testWords(string $raw, array $words): void
    {
        self::assertSame($words, SettlementQuery::parse($raw)?->words);
    }

    public static function empties(): array
    {
        return [[''], ['   '], ['місто'], ["\u{200B}"]];
    }

    #[DataProvider('empties')]
    public function testNothingToSearch(string $raw): void
    {
        self::assertNull(SettlementQuery::parse($raw));
    }

    public function testVeryLongInputIsCut(): void
    {
        $query = SettlementQuery::parse(str_repeat('ї', 500));

        self::assertSame(64, mb_strlen($query->words[0]));
    }

    public function testFirstWordIsThePrefix(): void
    {
        self::assertSame('біла', SettlementQuery::parse('Біла Церква')->prefix);
    }
}
