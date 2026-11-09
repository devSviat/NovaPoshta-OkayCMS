<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Extenders\FrontExtender;
use PHPUnit\Framework\TestCase;

/** What the cart opens with: the posted form, else the choice priced in this session, else the last order. */
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

    /** The page prices the last order's address on load; a reload must not lose its street and house. */
    public function testSessionInTheLastOrdersCityKeepsItsAddress(): void
    {
        self::assertSame(['settlement_id' => 118064, 'street' => 'вул. Тестова', 'house' => '5', 'flat' => '12'], FrontExtender::prefillChoice(null, ['settlement_id' => 118064], self::last()));
        self::assertSame(['settlement_id' => 9], FrontExtender::prefillChoice(null, ['settlement_id' => 9], self::last()), 'another city: no old street');
    }
}
