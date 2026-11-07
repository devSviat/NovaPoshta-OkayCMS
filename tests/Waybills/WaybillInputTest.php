<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillInput;
use PHPUnit\Framework\TestCase;

class WaybillInputTest extends TestCase
{
    public function testOnlyFormFieldsPass(): void
    {
        $input = WaybillInput::fromPost(['recipient_name' => 'Петренко Іван', 'order_id' => '5', 'api' => 'old', 'session_id' => 'x']);

        self::assertSame(['recipient_name' => 'Петренко Іван'], $input);
    }

    public function testTextIsLimited(): void
    {
        $input = WaybillInput::fromPost(['recipient_name' => str_repeat('я', 500), 'phone' => str_repeat('1', 500), 'address_note' => str_repeat('ж', 500), 'description' => str_repeat('о', 500)]);

        self::assertSame(60, mb_strlen($input['recipient_name']));
        self::assertSame(20, mb_strlen($input['phone']));
        self::assertSame(50, mb_strlen($input['address_note']));
        self::assertSame(100, mb_strlen($input['description']));
    }

    public function testNumbersAndSwitches(): void
    {
        $input = WaybillInput::fromPost(['weight' => '1,5', 'cod' => 'on', 'lifting' => '1', 'cod_amount' => 'abc']);

        self::assertSame(1.5, $input['weight']);
        self::assertTrue($input['cod']);
        self::assertTrue($input['lifting']);
        self::assertSame('abc', $input['cod_amount']);
    }

    public function testArraysAreDropped(): void
    {
        self::assertSame([], WaybillInput::fromPost(['recipient_name' => ['x'], 'weight' => ['1'], 'cod' => ['1']]));
    }
}
