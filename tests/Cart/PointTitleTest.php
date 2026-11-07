<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Cart\PointTitle;
use PHPUnit\Framework\TestCase;

class PointTitleTest extends TestCase
{
    public function testDropsWeightLimit(): void
    {
        self::assertSame('Відділення №130', PointTitle::short('Відділення №130 (до 30 кг на одне місце)'));
        self::assertSame('Відділення №4', PointTitle::short('Відділення №4 (до 200 кг)'));
        self::assertSame('Branch No 4', PointTitle::short('Branch No 4 (up to 200 kg)'));
    }

    public function testShortensPostomat(): void
    {
        self::assertSame('Поштомат №1001', PointTitle::short('Поштомат "Нова Пошта" №1001'));
        self::assertSame('Parcel Locker No 1001', PointTitle::short('Parcel Locker "Nova Poshta" No 1001'));
    }

    public function testKeepsOther(): void
    {
        self::assertSame('Пункт №948', PointTitle::short('Пункт №948'));
        self::assertSame('Відділення №1 (Mega Сіті)', PointTitle::short('Відділення №1 (Mega Сіті)'));
    }
}
