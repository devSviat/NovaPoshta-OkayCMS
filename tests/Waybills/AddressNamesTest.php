<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Waybills\AddressNames;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Випадки перенесено з NovaPoshtaTracking AddressByReferenceTest::streetProvider. */
class AddressNamesTest extends TestCase
{
    #[DataProvider('streets')]
    public function testStreetCore(string $street, string $core, ?string $type): void
    {
        self::assertSame($core, AddressNames::streetCore($street));
        self::assertSame($type, AddressNames::streetType($street));
    }

    public static function streets(): array
    {
        return [
            'type in front' => ['вул. Городоцька', 'городоцька', 'вул'],
            'type after the name' => ['Городоцька вул.', 'городоцька', 'вул'],
            'full word' => ['вулиця Шевченка', 'шевченка', 'вул'],
            'avenue' => ['просп. Свободи', 'свободи', 'просп'],
            'no type' => ['Івасюка', 'івасюка', null],
            'apostrophe' => ['вул. Об’їзна', "об'їзна", 'вул'],
            'name starting with «пл»' => ['Плиткова', 'плиткова', null],
        ];
    }
}
