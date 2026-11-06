<?php

namespace Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Quote\DeliveryQuote;
use Okay\Modules\Sviat\NovaPoshta\Quote\PriceDecision;
use PHPUnit\Framework\TestCase;

class PriceDecisionTest extends TestCase
{
    private const array CORE = ['delivery_price' => 70.0, 'separate_delivery' => 0];

    public function testCalculatedPriceGoesToTheOrder(): void
    {
        $decision = PriceDecision::forOrder(new DeliveryQuote(135.0, null, 'old', null, 40.0), self::CORE);

        self::assertSame(['delivery_price' => 135.0, 'separate_delivery' => 0], $decision['info']);
        self::assertSame('old', $decision['source']);
    }

    public function testFailedCalculationKeepsTheAdminPrice(): void
    {
        $decision = PriceDecision::forOrder(new DeliveryQuote(null, null, 'fallback', 'unreachable'), self::CORE);

        self::assertSame(self::CORE, $decision['info']);
        self::assertSame('fallback', $decision['source']);
    }

    public function testNoQuoteMeansTheCoreDecides(): void
    {
        self::assertSame(['info' => self::CORE, 'source' => null], PriceDecision::forOrder(null, self::CORE));
        self::assertSame(['info' => self::CORE, 'source' => 'none'], PriceDecision::forOrder(new DeliveryQuote(null, null, 'none', null), self::CORE));
    }

    public function testFreeByTheCoreStaysFree(): void
    {
        $free = ['delivery_price' => 0, 'separate_delivery' => 0];

        self::assertSame($free, PriceDecision::forOrder(new DeliveryQuote(95.0, null, 'new', null), $free, freeByCore: true)['info']);
    }

    public function testStaleOrForeignQuoteIsRecalculated(): void
    {
        // The caller asks CartQuotes with the order's own point, payment and cart; a quote made for
        // anything else has another key and is never found — the decision only sees the fresh one.
        $fresh = new DeliveryQuote(99.0, null, 'new', null);

        self::assertSame(99.0, PriceDecision::forOrder($fresh, self::CORE)['info']['delivery_price']);
    }

    public function testPriceSetByAnotherModuleIsKept(): void
    {
        // e.g. a promo's free shipping: the core alone would give 70, the chain already gave 0.
        $promo = ['delivery_price' => 0, 'separate_delivery' => 0];

        self::assertSame(['info' => $promo, 'source' => 'external'], PriceDecision::forOrder(new DeliveryQuote(172.0, null, 'new', null), $promo, coreDefault: 70.0));
    }
}
