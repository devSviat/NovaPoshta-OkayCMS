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

    /** Один відвідувач без cookie не вичерпує спільний ліміт: його частка обмежена, іншим лишається місце. */
    public function testOneVisitorCannotTakeTheWholeBudget(): void
    {
        $state = null;
        for ($i = 0; $i < QuoteBudget::VISITOR_LIMIT; $i++) {
            [$ok, $state] = QuoteBudget::take($state, 1000, visitor: 'bot');
            self::assertTrue($ok);
        }
        [$ok, $state] = QuoteBudget::take($state, 1001, visitor: 'bot');
        self::assertFalse($ok, 'частка відвідувача вичерпана');
        [$ok, $state] = QuoteBudget::take($state, 1002, visitor: 'shopper');
        self::assertTrue($ok, 'інший покупець рахує далі');
        [$ok] = QuoteBudget::take($state, 1000 + QuoteBudget::WINDOW, visitor: 'bot');
        self::assertTrue($ok, 'нове вікно');
    }

    public function testVisitorIsAnIpHash(): void
    {
        self::assertNotNull(QuoteBudget::visitor('203.0.113.7'));
        self::assertNotSame(QuoteBudget::visitor('203.0.113.7'), QuoteBudget::visitor('203.0.113.8'));
        self::assertSame(QuoteBudget::visitor('203.0.113.7'), QuoteBudget::visitor('::ffff:203.0.113.7'), 'IPv4 on a dual-stack socket');
        self::assertNull(QuoteBudget::visitor(null));
        self::assertNull(QuoteBudget::visitor(''));
        self::assertLessThanOrEqual(QuoteBudget::LIMIT / 3, QuoteBudget::VISITOR_LIMIT);
    }

    /** Клієнт IPv6, що міняє адреси в межах своєї /64, лишається одним відвідувачем. */
    public function testIpv6AddressesInOneSlash64ShareOneVisitor(): void
    {
        $first = QuoteBudget::visitor('2001:db8:aa:1::1');
        self::assertSame($first, QuoteBudget::visitor('2001:db8:aa:1:ffff:1234:5678:9abc'));
        self::assertNotSame($first, QuoteBudget::visitor('2001:db8:aa:2::1'));
    }
}
