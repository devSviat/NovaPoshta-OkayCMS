<?php

namespace Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Quote\DeliveryQuote;
use PHPUnit\Framework\TestCase;

class DeliveryQuoteTest extends TestCase
{
    public function testOnlyAFailedCalculationIsUnpriced(): void
    {
        self::assertTrue(new DeliveryQuote(null, null, 'fallback', 'rejected')->unpriced());
        self::assertTrue(new DeliveryQuote(null, null, 'fallback', 'no_city_ref')->unpriced());
        self::assertFalse(new DeliveryQuote(95.0, null, 'new', null)->unpriced());
        self::assertFalse(new DeliveryQuote(null, null, 'none', 'no_sender')->unpriced(), 'no price was asked: the admin price stands');
        self::assertFalse(new DeliveryQuote(null, null, 'fallback', 'busy')->unpriced(), 'busy: checkout prices it anyway');
        self::assertFalse(new DeliveryQuote(null, null, 'none', 'no_city_ref')->unpriced(), 'term-only failure: no price was asked');
    }
}
