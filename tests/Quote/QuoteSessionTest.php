<?php

namespace Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Quote\DeliveryQuote;
use Okay\Modules\Sviat\NovaPoshta\Quote\QuoteKey;
use Okay\Modules\Sviat\NovaPoshta\Quote\QuoteSession;
use Okay\Modules\Sviat\NovaPoshta\Quote\QuoteThrottle;
use PHPUnit\Framework\TestCase;

class QuoteSessionTest extends TestCase
{
    private const int NOW = 1_900_000_000;

    private static function cart(array $lines, float $total): object
    {
        return (object)['total_price' => $total, 'purchases' => array_map(static fn(array $l): object => (object)['variant_id' => $l[0], 'amount' => $l[1]], $lines)];
    }

    public function testKeyFollowsEveryInputButNotTheOrderOfLines(): void
    {
        $cart = self::cart([[1, 2], [5, 1]], 300.0);
        $key = QuoteKey::for(4, 7162, false, $cart);

        self::assertSame($key, QuoteKey::for(4, 7162, false, self::cart([[5, 1], [1, 2]], 300.0)));
        self::assertNotSame($key, QuoteKey::for(5, 7162, false, $cart));
        self::assertNotSame($key, QuoteKey::for(4, 7163, false, $cart));
        self::assertNotSame($key, QuoteKey::for(4, 7162, true, $cart));
        self::assertNotSame($key, QuoteKey::for(4, 7162, false, self::cart([[1, 2], [5, 1]], 301.0)));
    }

    public function testCartChangeChangesKey(): void
    {
        self::assertNotSame(
            QuoteKey::for(4, 7162, false, self::cart([[1, 2]], 300.0)),
            QuoteKey::for(4, 7162, false, self::cart([[1, 3]], 300.0)),
        );
    }

    public function testQuoteLivesAnHour(): void
    {
        $bag = [];
        $quote = new DeliveryQuote(135.0, new \DateTimeImmutable('2030-03-18'), 'old', null, 40.0);
        QuoteSession::put($bag, 'k', $quote, self::NOW);

        $back = QuoteSession::get($bag, 'k', self::NOW + 3599);
        self::assertEquals($quote, $back);
        self::assertNull(QuoteSession::get($bag, 'k', self::NOW + 3601));
        self::assertNull(QuoteSession::get($bag, 'other', self::NOW));
    }

    public function testAtMostTenQuotesAreKept(): void
    {
        $bag = [];
        for ($i = 0; $i < 12; $i++) {
            QuoteSession::put($bag, "k{$i}", new DeliveryQuote((float)$i, null, 'new', null), self::NOW + $i);
        }

        self::assertNull(QuoteSession::get($bag, 'k0', self::NOW + 20));
        self::assertNull(QuoteSession::get($bag, 'k1', self::NOW + 20));
        self::assertSame(11.0, QuoteSession::get($bag, 'k11', self::NOW + 20)?->price);
    }

    public function testBrokenSessionDataIsIgnored(): void
    {
        $bag = ['quotes' => ['k' => 'garbage'], 'chosen' => 'x'];

        self::assertNull(QuoteSession::get($bag, 'k', self::NOW));
        self::assertNull(QuoteSession::chosen($bag, 4));
    }

    public function testLastChoicePerDelivery(): void
    {
        $bag = [];
        QuoteSession::choose($bag, 4, 7162, true);
        QuoteSession::choose($bag, 9, 1, false);

        self::assertSame(['divisionId' => 7162, 'cod' => true], QuoteSession::chosen($bag, 4));
        self::assertNull(QuoteSession::chosen($bag, 5));
    }

    public function testThrottle(): void
    {
        $bag = [];
        for ($i = 0; $i < 20; $i++) {
            self::assertTrue(QuoteThrottle::allow($bag, self::NOW + $i));
        }
        self::assertFalse(QuoteThrottle::allow($bag, self::NOW + 30));
        self::assertTrue(QuoteThrottle::allow($bag, self::NOW + 601));
    }
}
