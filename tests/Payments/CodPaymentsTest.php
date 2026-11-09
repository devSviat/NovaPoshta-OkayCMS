<?php

namespace Modules\Sviat\NovaPoshta\Payments;

use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;
use PHPUnit\Framework\TestCase;

class CodPaymentsTest extends TestCase
{
    public function testFlagsMapToKinds(): void
    {
        self::assertSame(CodKind::Money, CodKind::fromFlag(2));
        self::assertSame(CodKind::Control, CodKind::fromFlag(3));
        self::assertNull(CodKind::fromFlag(0));
        self::assertNull(CodKind::fromFlag(7));
        self::assertSame(2, CodKind::Money->flag());
        self::assertSame(3, CodKind::Control->flag());
    }

    /** 1.0.0 has no shop-wide kind to fall back on: a flag that names no kind is no cash on delivery. */
    public function testFlagOneIsNotCashOnDelivery(): void
    {
        self::assertNull(CodKind::fromFlag(1));
    }

    public function testManualModuleValues(): void
    {
        self::assertTrue(CodPayments::isManual(null));
        self::assertTrue(CodPayments::isManual(''));
        self::assertTrue(CodPayments::isManual('null'), 'the core stores "Manual" as the string null');
        self::assertFalse(CodPayments::isManual('OkayCMS/LiqPay'));
    }

    public function testOnlinePaymentIsNeverCod(): void
    {
        self::assertNull(CodPayments::kindOf((object)['module' => 'OkayCMS/LiqPay', CodPayments::FIELD => 3]));
        self::assertSame(CodKind::Control, CodPayments::kindOf((object)['module' => 'null', CodPayments::FIELD => 3]));
        self::assertNull(CodPayments::kindOf((object)['module' => 'null']), 'no value means no cash on delivery');
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

    /** A switched-off method is not offered in the cart, so its commission is not shown there either. */
    public function testDisabledMethodHasNoCartCommission(): void
    {
        $entity = $this->createStub(\Okay\Entities\PaymentsEntity::class);
        $entity->method('noLimit')->willReturnSelf();
        $entity->method('find')->willReturn([
            (object)['id' => 18, 'module' => 'null', 'enabled' => 1, CodPayments::FIELD => 2],
            (object)['id' => 19, 'module' => 'null', 'enabled' => 0, CodPayments::FIELD => 2],
            (object)['id' => 20, 'module' => 'null', 'enabled' => '1', CodPayments::FIELD => 3],
        ]);
        $factory = $this->createStub(\Okay\Core\EntityFactory::class);
        $factory->method('get')->willReturn($entity);

        self::assertSame([18], new CodPayments($factory)->moneyIds());
    }
}
