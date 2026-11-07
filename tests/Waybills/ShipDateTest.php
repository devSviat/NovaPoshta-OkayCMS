<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Waybills\ShipDate;
use PHPUnit\Framework\TestCase;

class ShipDateTest extends TestCase
{
    public function testBeforeTheHourItIsToday(): void
    {
        self::assertSame('07.11.2026', ShipDate::for(new \DateTimeImmutable('2026-11-07 15:59'), '16:00'));
    }

    public function testFromTheHourItIsTomorrow(): void
    {
        self::assertSame('08.11.2026', ShipDate::for(new \DateTimeImmutable('2026-11-07 16:00'), '16:00'));
        self::assertSame('01.01.2027', ShipDate::for(new \DateTimeImmutable('2026-12-31 23:00'), '16:00'));
    }

    public function testNoHourMeansTomorrow(): void
    {
        self::assertSame('08.11.2026', ShipDate::for(new \DateTimeImmutable('2026-11-07 08:00'), ''));
    }
}
