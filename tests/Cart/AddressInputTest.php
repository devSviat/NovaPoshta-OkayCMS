<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Cart\AddressInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AddressInputTest extends TestCase
{
    private static function post(array $fields): array
    {
        return ['novaposhta' => [4 => $fields + ['settlement_id' => '118064', 'street' => 'вул. Хрещатик', 'house' => '1']]];
    }

    public function testFullAddress(): void
    {
        $parsed = AddressInput::parse(self::post([
            'street_ref' => '0f0d85b0-4143-11dd-9198-001d60451983', 'house' => ' 1А ', 'flat' => '5', 'entrance' => '2',
            'floor' => '7', 'elevator' => '1', 'note' => "домофон\u{200B} 5", 'lifting' => '1',
        ]), 4, true);

        self::assertNull($parsed['error']);
        $a = $parsed['address'];
        self::assertSame([118064, 'вул. Хрещатик', '0f0d85b0-4143-11dd-9198-001d60451983', '1А', '5', '2', 7, true, 'домофон 5', true],
            [$a->settlementId, $a->street, $a->streetRef, $a->house, $a->flat, $a->entrance, $a->floor, $a->elevator, $a->note, $a->lifting]);
    }

    public function testOptionalFieldsMayBeEmpty(): void
    {
        $a = AddressInput::parse(self::post(['flat' => '', 'floor' => '']), 4, false)['address'];

        self::assertSame([null, null, null, false, null, false], [$a->flat, $a->entrance, $a->floor, $a->elevator, $a->note, $a->lifting]);
    }

    public static function errors(): array
    {
        return [
            'no city' => [['settlement_id' => ''], 'error_city'],
            'no street' => [['street' => ' '], 'error_street'],
            'one-letter street' => [['street' => 'Х'], 'error_street'],
            'no house' => [['house' => ''], 'error_house'],
            'long house' => [['house' => str_repeat('1', 11)], 'error_address_too_long'],
            'long street' => [['street' => str_repeat('в', 101)], 'error_address_too_long'],
            'long flat' => [['flat' => str_repeat('1', 11)], 'error_address_too_long'],
            'long entrance' => [['entrance' => '123456'], 'error_address_too_long'],
            'long note' => [['note' => str_repeat('н', 41)], 'error_address_too_long'],
            'floor zero' => [['floor' => '0'], 'error_floor'],
            'floor text' => [['floor' => 'сьомий'], 'error_floor'],
            'floor 201' => [['floor' => '201'], 'error_floor'],
            'html in note' => [['note' => '<b>x</b>'], 'error_address_characters'],
            'html in house' => [['house' => '1<'], 'error_address_characters'],
            'array instead of text' => [['street' => ['x']], 'error_street'],
        ];
    }

    #[DataProvider('errors')]
    public function testErrors(array $fields, string $error): void
    {
        self::assertSame($error, AddressInput::parse(self::post($fields), 4, true)['error']);
    }

    public function testLiftingNeedsAFloor(): void
    {
        self::assertSame('error_lifting_floor', AddressInput::parse(self::post(['lifting' => '1', 'floor' => '']), 4, true)['error']);
    }

    public function testLiftingOffIsIgnored(): void
    {
        $parsed = AddressInput::parse(self::post(['lifting' => '1', 'floor' => '']), 4, false);

        self::assertNull($parsed['error']);
        self::assertFalse($parsed['address']->lifting);
    }

    public function testStreetRefMustLookLikeARef(): void
    {
        $a = AddressInput::parse(self::post(['street_ref' => "x' OR 1"]), 4, false)['address'];

        self::assertNull($a->streetRef);
        self::assertSame('вул. Хрещатик', $a->street);
    }

    public function testOnlyThisDeliveryMethodIsRead(): void
    {
        self::assertSame('error_city', AddressInput::parse(['novaposhta' => [9 => ['settlement_id' => '1', 'street' => 'вул. Х', 'house' => '1']]], 4, false)['error']);
    }
}
