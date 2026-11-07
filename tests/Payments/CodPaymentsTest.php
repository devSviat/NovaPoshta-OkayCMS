<?php

namespace Modules\Sviat\NovaPoshta\Payments;

use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;
use PHPUnit\Framework\TestCase;

class CodPaymentsTest extends TestCase
{
    public function testFlagsMapToKinds(): void
    {
        self::assertSame(CodKind::Money, CodKind::fromFlag(2, null));
        self::assertSame(CodKind::Control, CodKind::fromFlag(3, null));
        self::assertNull(CodKind::fromFlag(0, CodKind::Control));
        self::assertNull(CodKind::fromFlag(7, CodKind::Control));
        self::assertSame(2, CodKind::Money->flag());
        self::assertSame(3, CodKind::Control->flag());
    }

    public function testLegacyFlagFollowsOldGlobalKind(): void
    {
        self::assertSame(CodKind::Control, CodKind::fromFlag(1, CodKind::Control), 'a shop on payment control keeps it until the update runs');
        self::assertSame(CodKind::Money, CodKind::fromFlag(1, null));
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
        self::assertNull(CodPayments::kindOf((object)['module' => 'OkayCMS/LiqPay', CodPayments::FIELD => 3], null));
        self::assertSame(CodKind::Control, CodPayments::kindOf((object)['module' => 'null', CodPayments::FIELD => 3], null));
        self::assertNull(CodPayments::kindOf((object)['module' => 'null'], CodKind::Money), 'no column yet means no cash on delivery');
    }

    public function testMigrateLegacyMapsByOldGlobalKind(): void
    {
        self::assertSame([10 => 3, 11 => 3], CodPayments::migratedFlags([10 => 1, 11 => 1, 12 => 0, 13 => 3], CodKind::Control));
        self::assertSame([10 => 2, 11 => 2], CodPayments::migratedFlags([10 => 1, 11 => 1, 12 => 0, 13 => 3], null));
        self::assertSame([], CodPayments::migratedFlags([12 => 0, 13 => 2], CodKind::Control), 'a second run changes nothing');
    }

    public function testUpdateBuildsItFromCoreServicesOnly(): void
    {
        // A disabled module's Init/services.php is not loaded, yet "Update" still runs update_0_10_0.
        $core = [
            \Okay\Core\EntityFactory::class => (new \ReflectionClass(\Okay\Core\EntityFactory::class))->newInstanceWithoutConstructor(),
            \Okay\Core\Settings::class => (new \ReflectionClass(\Okay\Core\Settings::class))->newInstanceWithoutConstructor(),
        ];
        $get = static fn(string $id): object => $core[$id] ?? throw new \RuntimeException("not a core service: $id");

        self::assertInstanceOf(CodPayments::class, CodPayments::forUpdate($get));
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
