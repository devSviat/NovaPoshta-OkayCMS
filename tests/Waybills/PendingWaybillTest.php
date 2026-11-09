<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Waybills\PendingWaybill;
use PHPUnit\Framework\TestCase;

/** Накладна, що створюється в НП, ще не має номера: саме це, а не значення `api`, означає "pending". */
class PendingWaybillTest extends TestCase
{
    public function testNoNumberMeansBeingMade(): void
    {
        self::assertTrue(PendingWaybill::is((object)['number' => null, 'api' => null]));
        self::assertFalse(PendingWaybill::is((object)['number' => '20450000000001', 'api' => 'new']));
        self::assertFalse(PendingWaybill::is((object)['number' => '20450000000001', 'api' => 'attached']));
        self::assertFalse(PendingWaybill::is(null), 'no row is no waybill, not one being made');
    }
}
