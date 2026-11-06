<?php

namespace Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Quote\QuoteBudget;
use PHPUnit\Framework\TestCase;

class QuoteBudgetTest extends TestCase
{
    public function testShopWideWindow(): void
    {
        $state = null;
        for ($i = 0; $i < QuoteBudget::LIMIT; $i++) {
            [$ok, $state] = QuoteBudget::take($state, 1000 + $i);
            self::assertTrue($ok);
        }
        [$ok, $state] = QuoteBudget::take($state, 1100);
        self::assertFalse($ok, 'sessions without cookies all share this budget');
        [$ok] = QuoteBudget::take($state, 1000 + QuoteBudget::WINDOW);
        self::assertTrue($ok, 'a new window starts');
    }

    public function testGarbageStateStartsOver(): void
    {
        [$ok, $state] = QuoteBudget::take('{"start":"x"}', 5);

        self::assertTrue($ok);
        self::assertSame(['start' => 5, 'count' => 1], json_decode($state, true));
    }

    public function testOwnLimit(): void
    {
        [$ok, $state] = QuoteBudget::take(null, 5, 1);
        self::assertTrue($ok);
        self::assertFalse(QuoteBudget::take($state, 6, 1)[0]);
    }
}
