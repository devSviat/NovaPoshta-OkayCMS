<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Waybills\NpPhone;
use PHPUnit\Framework\TestCase;

class NpPhoneTest extends TestCase
{
    public function testPhoneFormats(): void
    {
        self::assertSame('380500000000', NpPhone::normalize('+38 (050) 000-00-00'));
        self::assertSame('380500000000', NpPhone::normalize('0500000000'));
        self::assertSame('380500000000', NpPhone::normalize('380500000000'));
        self::assertNull(NpPhone::normalize('+48500000000'));
        self::assertNull(NpPhone::normalize(''));
        self::assertNull(NpPhone::normalize('050 000 00'));
    }
}
