<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillNumber;
use PHPUnit\Framework\TestCase;

class WaybillNumberTest extends TestCase
{
    public function testSpacesAndDashesAreDropped(): void
    {
        self::assertSame('20450012345678', WaybillNumber::normalize('20 4500 1234 5678'));
        self::assertSame('20450012345678', WaybillNumber::normalize('2045-0012-3456-78'));
    }

    public function testOnlyFourteenDigitsAreANumber(): void
    {
        self::assertNull(WaybillNumber::normalize('2045001234567'));
        self::assertNull(WaybillNumber::normalize('204500123456789'));
        self::assertNull(WaybillNumber::normalize(''));
        self::assertNull(WaybillNumber::normalize('2045001234567a'));
    }
}
