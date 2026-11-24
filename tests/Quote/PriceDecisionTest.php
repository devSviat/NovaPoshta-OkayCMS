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

    public function testFailedCalculationChargesNoDelivery(): void
    {
        foreach (['unreachable', 'rejected', 'no_city_ref', 'no_uah'] as $problem) {
            $decision = PriceDecision::forOrder(new DeliveryQuote(null, null, 'fallback', $problem), self::CORE, coreDefault: 70.0);

            self::assertSame(['delivery_price' => 0.0, 'separate_delivery' => 0], $decision['info'], $problem);
            self::assertSame('fallback', $decision['source']);
        }
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

    /** Кошик показав «безкоштовно»: знімок це запам'ятовує, і ТТН потім не перевіряє поріг заново. */
    public function testFreeByTheCoreIsRecordedAsAPromise(): void
    {
        $free = ['delivery_price' => 0, 'separate_delivery' => 1];

        self::assertSame('free', PriceDecision::forOrder(new DeliveryQuote(95.0, null, 'new', null), $free, freeByCore: true)['source']);
        self::assertSame('free', PriceDecision::forOrder(new DeliveryQuote(null, null, 'none', null), $free, freeByCore: true)['source'], 'price hidden in the cart');
        self::assertSame('free', PriceDecision::forOrder(null, $free, freeByCore: true)['source'], 'no quote for the point');
        self::assertSame('new', PriceDecision::forOrder(new DeliveryQuote(95.0, null, 'new', null), self::CORE)['source'], 'control: below the threshold');
    }

    public function testFreeMethodIsAPromiseOnlyAtZero(): void
    {
        $zero = ['delivery_price' => 0, 'separate_delivery' => 0];

        self::assertSame('free', PriceDecision::forOrder(null, $zero, freeMethod: true)['source']);
        self::assertNotSame('free', PriceDecision::forOrder(null, self::CORE, freeMethod: true)['source'], 'a "free" method still charging its price');
        self::assertNotSame('free', PriceDecision::forOrder(new DeliveryQuote(null, null, 'fallback', 'unreachable'), self::CORE, coreDefault: 70.0, freeMethod: true)['source'], 'unpriced is not free');
    }

    public function testExternalWinsOverFree(): void
    {
        $promo = ['delivery_price' => 0, 'separate_delivery' => 0];

        self::assertSame('external', PriceDecision::forOrder(new DeliveryQuote(172.0, null, 'new', null), $promo, freeByCore: true, coreDefault: 70.0)['source']);
    }

    public function testStaleOrForeignQuoteIsRecalculated(): void
    {
        // Викликач питає CartQuotes з власними пунктом видачі, оплатою й кошиком замовлення; розрахунок
        // для чогось іншого має інший ключ і ніколи не знаходиться — рішення бачить лише свіжий.
        $fresh = new DeliveryQuote(99.0, null, 'new', null);

        self::assertSame(99.0, PriceDecision::forOrder($fresh, self::CORE)['info']['delivery_price']);
    }

    public function testPriceSetByAnotherModuleIsKept(): void
    {
        // напр. безкоштовна доставка акції: саме ядро дало б 70, ланцюжок уже дав 0.
        $promo = ['delivery_price' => 0, 'separate_delivery' => 0];

        self::assertSame(['info' => $promo, 'source' => 'external'], PriceDecision::forOrder(new DeliveryQuote(172.0, null, 'new', null), $promo, coreDefault: 70.0));
    }
}
