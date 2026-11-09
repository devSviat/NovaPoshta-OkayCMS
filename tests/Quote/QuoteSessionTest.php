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
        $key = QuoteKey::for(4, 'd:7162', false, $cart);

        self::assertSame($key, QuoteKey::for(4, 'd:7162', false, self::cart([[5, 1], [1, 2]], 300.0)));
        self::assertNotSame($key, QuoteKey::for(5, 'd:7162', false, $cart));
        self::assertNotSame($key, QuoteKey::for(4, 'd:7163', false, $cart));
        self::assertNotSame($key, QuoteKey::for(4, 'd:7162', true, $cart));
        self::assertNotSame($key, QuoteKey::for(4, 'd:7162', false, self::cart([[1, 2], [5, 1]], 301.0)));
    }

    public function testKeysFromBeforeTheCommissionWasSeparatedAreNotReused(): void
    {
        // Ключі 0.5.x мали ті самі частини; їхні ціни ще включають комісію післяплати.
        $legacy = sha1(implode('|', [4, 'd:7162', 1, 0, '1:2', '300.00']));

        self::assertNotSame($legacy, QuoteKey::for(4, 'd:7162', true, self::cart([[1, 2]], 300.0)));
    }

    public function testCartChangeChangesKey(): void
    {
        self::assertNotSame(
            QuoteKey::for(4, 'd:7162', false, self::cart([[1, 2]], 300.0)),
            QuoteKey::for(4, 'd:7162', false, self::cart([[1, 3]], 300.0)),
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
        QuoteSession::choose($bag, 4, 'd:7162', true);
        QuoteSession::choose($bag, 9, 'a:118064', false, true);

        self::assertSame(['target' => 'd:7162', 'cod' => true, 'lifting' => false, 'floor' => null, 'elevator' => false], QuoteSession::chosen($bag, 4));
        self::assertSame(['target' => 'a:118064', 'cod' => false, 'lifting' => true, 'floor' => null, 'elevator' => false], QuoteSession::chosen($bag, 9));
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

    public function testStreetLookupsHaveTheirOwnSlotAndLimit(): void
    {
        $bag = [];
        for ($i = 0; $i < 20; $i++) {
            QuoteThrottle::allow($bag, self::NOW);
        }
        for ($i = 0; $i < 60; $i++) {
            self::assertTrue(QuoteThrottle::allow($bag, self::NOW, 'street_calls', 60));
        }
        self::assertFalse(QuoteThrottle::allow($bag, self::NOW, 'street_calls', 60));
        self::assertFalse(QuoteThrottle::allow($bag, self::NOW), 'the quote slot is spent separately');
    }

    public function testKeyFollowsTargetAndLifting(): void
    {
        $cart = self::cart([[1, 1]], 100.0);

        self::assertNotSame(QuoteKey::for(4, 'a:118064', false, $cart), QuoteKey::for(4, 'd:118064', false, $cart));
        self::assertNotSame(QuoteKey::for(4, 'a:118064', false, $cart), QuoteKey::for(4, 'a:118064', false, $cart, true));
    }

    public function testUnreachableIsKeptForTheOrderButRetriedByTheCart(): void
    {
        $bag = [];
        QuoteSession::put($bag, 'k', new DeliveryQuote(null, null, 'fallback', 'unreachable'), self::NOW);

        self::assertSame('unreachable', QuoteSession::get($bag, 'k', self::NOW + 59, retryUnreachableAfter: 60)?->problem);
        self::assertNull(QuoteSession::get($bag, 'k', self::NOW + 60, retryUnreachableAfter: 60));
        self::assertSame('unreachable', QuoteSession::get($bag, 'k', self::NOW + 600)?->problem, 'checkout keeps what the cart showed');
    }

    /**
     * Що кошик показує знову після перезавантаження: пункт видачі або населений пункт, для якого покупець востаннє
     * розраховував ціну.
     */
    public function testTargetOfTheChoice(): void
    {
        self::assertSame(['division_id' => 11213], QuoteSession::targetIds('d:11213'));
        self::assertSame(['settlement_id' => 110248], QuoteSession::targetIds('a:110248'));
        self::assertSame([], QuoteSession::targetIds('x:1'));
        self::assertSame([], QuoteSession::targetIds('d:'));
        self::assertSame([], QuoteSession::targetIds('d:-5'));
    }

    /**
     * НП рахує ціну підйому на поверх за поверхом і ліфтом: розрахунок для одного поверху не повинен давати ціну
     * іншого.
     */
    public function testLiftingKeyCoversFloorAndElevator(): void
    {
        $cart = self::cart([[1, 2]], 300.0);
        $key = QuoteKey::for(4, 'a:118064', false, $cart, true, 1, true);

        self::assertNotSame($key, QuoteKey::for(4, 'a:118064', false, $cart, true, 200, true));
        self::assertNotSame($key, QuoteKey::for(4, 'a:118064', false, $cart, true, 1, false));
        self::assertSame(QuoteKey::for(4, 'a:118064', false, $cart), QuoteKey::for(4, 'a:118064', false, $cart, false, 9, true), 'without lifting the floor does not change the price');
    }

    public function testChoiceKeepsFloorAndElevator(): void
    {
        $bag = [];
        QuoteSession::choose($bag, 9, 'a:118064', false, true, 5, true);

        self::assertSame(['target' => 'a:118064', 'cod' => false, 'lifting' => true, 'floor' => 5, 'elevator' => true], QuoteSession::chosen($bag, 9));
    }
}
