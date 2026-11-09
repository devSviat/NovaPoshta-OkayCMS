<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Extenders\FrontExtender;
use PHPUnit\Framework\TestCase;

/** З чим відкривається кошик: надіслана форма, інакше вибір, розрахований у цій сесії, інакше останнє замовлення. */
class PrefillChoiceTest extends TestCase
{
    private static function last(): object
    {
        return (object)['mode' => 'address', 'settlement_id' => 118064, 'division_id' => null, 'street_name' => 'вул. Тестова', 'house' => '5', 'flat' => '12'];
    }

    public function testPostedWins(): void
    {
        self::assertSame(['settlement_id' => 7], FrontExtender::prefillChoice(['settlement_id' => 7], ['settlement_id' => 8], self::last()));
    }

    public function testLastOrderWhenNothingElse(): void
    {
        self::assertSame(['settlement_id' => 118064, 'division_id' => null, 'street' => 'вул. Тестова', 'house' => '5', 'flat' => '12'], FrontExtender::prefillChoice(null, [], self::last()));
        self::assertSame([], FrontExtender::prefillChoice(null, [], null));
    }

    /**
     * Під час завантаження сторінка розраховує ціну для адреси останнього замовлення; перезавантаження не повинне
     * губити її вулицю й будинок.
     */
    public function testSessionInTheLastOrdersCityKeepsItsAddress(): void
    {
        self::assertSame(['settlement_id' => 118064, 'street' => 'вул. Тестова', 'house' => '5', 'flat' => '12'], FrontExtender::prefillChoice(null, ['settlement_id' => 118064], self::last()));
        self::assertSame(['settlement_id' => 9], FrontExtender::prefillChoice(null, ['settlement_id' => 9], self::last()), 'another city: no old street');
    }
}
