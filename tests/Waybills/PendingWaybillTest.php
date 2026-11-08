<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Waybills\PendingWaybill;
use PHPUnit\Framework\TestCase;

/** A waybill being made at NP has no number yet: that, not a value of `api`, is what "pending" means. */
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
