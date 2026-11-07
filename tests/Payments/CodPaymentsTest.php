<?php

namespace Modules\Sviat\NovaPoshta\Payments;

use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;
use PHPUnit\Framework\TestCase;

class CodPaymentsTest extends TestCase
{
    public function testOnlyExistingIdsOnceEach(): void
    {
        self::assertSame([2, 5], CodPayments::normalize(['5', '2', 2, '99', 'abc', '-1', ['3']], [1, 2, 3, 5]));
    }

    public function testNothingPostedMeansNone(): void
    {
        self::assertSame([], CodPayments::normalize(null, [1, 2]));
        self::assertSame([], CodPayments::normalize('2', [1, 2]), 'a scalar is not a list of ids');
    }

    public function testDeliveryWithoutCodPaymentWantsNoFee(): void
    {
        self::assertFalse(CodPayments::wantsFee([6, 20], [18]));
        self::assertFalse(CodPayments::wantsFee([], [18]));
    }

    public function testDeliveryWithCodPaymentWantsFee(): void
    {
        self::assertTrue(CodPayments::wantsFee([6, 18], [18]));
        self::assertTrue(CodPayments::wantsFee(['18'], [18]), 'the core gives ids as strings');
    }
}
