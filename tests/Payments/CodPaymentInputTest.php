<?php

namespace Modules\Sviat\NovaPoshta\Payments;

use Okay\Modules\Sviat\NovaPoshta\Payments\CodPaymentInput;
use PHPUnit\Framework\TestCase;

class CodPaymentInputTest extends TestCase
{
    private const array ON = ['sviat_np_cod_form' => '1', 'sviat_np_cod' => '1'];

    public function testWithoutMarkerLeavesFieldAlone(): void
    {
        self::assertNull(CodPaymentInput::flag([], 'null'));
        self::assertNull(CodPaymentInput::flag(['sviat_np_cod' => '1', 'sviat_np_cod_kind' => 'control'], 'null'), 'the block was not on the page');
    }

    public function testSwitchOffStoresZero(): void
    {
        self::assertSame(0, CodPaymentInput::flag(['sviat_np_cod_form' => '1'], 'null'));
        self::assertSame(0, CodPaymentInput::flag(['sviat_np_cod_form' => '1', 'sviat_np_cod_kind' => 'control'], 'null'));
    }

    public function testControlIsStored(): void
    {
        self::assertSame(3, CodPaymentInput::flag(self::ON + ['sviat_np_cod_kind' => 'control'], 'null'));
        self::assertSame(2, CodPaymentInput::flag(self::ON + ['sviat_np_cod_kind' => 'money'], ''));
    }

    public function testOnlyKnownKindsAreStored(): void
    {
        self::assertSame(2, CodPaymentInput::flag(self::ON + ['sviat_np_cod_kind' => 'evil'], 'null'));
        self::assertSame(2, CodPaymentInput::flag(self::ON + ['sviat_np_cod_kind' => ['x']], 'null'));
        self::assertSame(2, CodPaymentInput::flag(self::ON, 'null'), 'switched on without a kind: money transfer works without a contract');
    }

    public function testOnlinePaymentStoresZero(): void
    {
        self::assertSame(0, CodPaymentInput::flag(self::ON + ['sviat_np_cod_kind' => 'control'], 'OkayCMS/LiqPay'));
    }
}
