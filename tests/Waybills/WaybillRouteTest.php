<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

require_once __DIR__ . '/../Support/WaybillFixtures.php';

use Modules\Sviat\NovaPoshta\Support\WaybillFixtures as W;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillRoute;
use PHPUnit\Framework\TestCase;

class WaybillRouteTest extends TestCase
{
    private const string TODAY = '07.11.2026';

    public function testPlainPointGoesToTheNewApi(): void
    {
        self::assertSame('new', WaybillRoute::for(W::point(), self::TODAY));
        self::assertSame('new', WaybillRoute::for(W::point(['cargoType' => 'Documents']), self::TODAY));
    }

    public function testCashOnDeliveryGoesToTheOldApi(): void
    {
        self::assertSame('old', WaybillRoute::for(W::point(['cod' => true]), self::TODAY));
        self::assertSame('old', WaybillRoute::for(W::point(['cod' => true, 'codKind' => CodKind::Control]), self::TODAY));
    }

    public function testAddressGoesToTheOldApi(): void
    {
        self::assertSame('old', WaybillRoute::for(W::address(), self::TODAY));
    }

    public function testWhatTheNewApiCannotSetGoesToTheOldApi(): void
    {
        self::assertSame('old', WaybillRoute::for(W::point(['paymentMethod' => 'NonCash']), self::TODAY));
        self::assertSame('old', WaybillRoute::for(W::point(['shipDate' => '08.11.2026']), self::TODAY));
    }
}
