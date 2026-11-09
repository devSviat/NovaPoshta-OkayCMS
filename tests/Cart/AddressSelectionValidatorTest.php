<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartSelectionValidator;
use PHPUnit\Framework\TestCase;

class AddressSelectionValidatorTest extends TestCase
{
    private const int MODULE = 74;

    private static function delivery(string $type): object
    {
        return (object)['id' => 4, 'module_id' => self::MODULE, 'settings' => ['service_type' => $type]];
    }

    private static function post(): array
    {
        return ['novaposhta' => [4 => ['settlement_id' => '118064', 'street' => 'вул. Хрещатик', 'house' => '1', 'division_id' => '7162']]];
    }

    private static function settlement(): object
    {
        return (object)['id' => 118064, 'name_uk' => 'місто Київ', 'district_uk' => null, 'area_uk' => 'Київська область'];
    }

    public function testAddressMethodGivesAnAddress(): void
    {
        $selection = CartSelectionValidator::checkAddress(self::delivery('WarehouseDoors'), self::MODULE, self::settlement(), self::post(), false);

        self::assertTrue($selection->applies());
        self::assertSame('вул. Хрещатик', $selection->address->street);
        self::assertNull($selection->division);
    }

    /**
     * До першого запуску довідників кошик показує лише повідомлення, як і для пунктів видачі: замовлення не
     * блокується.
     */
    public function testEmptyDictionariesDoNotBlockTheOrder(): void
    {
        $selection = CartSelectionValidator::checkAddress(self::delivery('WarehouseDoors'), self::MODULE, null, [], false, dictionaryReady: false);

        self::assertFalse($selection->applies());
    }

    public function testUnknownSettlement(): void
    {
        self::assertSame('error_city', CartSelectionValidator::checkAddress(self::delivery('DoorsDoors'), self::MODULE, null, self::post(), false));
    }

    public function testPointsMethodIgnoresAddressFields(): void
    {
        self::assertFalse(CartSelectionValidator::checkAddress(self::delivery('WarehouseWarehouse'), self::MODULE, self::settlement(), self::post(), false)->applies());
    }

    public function testForeignMethodIsIgnored(): void
    {
        $foreign = (object)['id' => 4, 'module_id' => 12, 'settings' => ['service_type' => 'WarehouseDoors']];

        self::assertFalse(CartSelectionValidator::checkAddress($foreign, self::MODULE, self::settlement(), self::post(), false)->applies());
    }

    public function testPointsCheckIgnoresAddressMethods(): void
    {
        self::assertFalse(CartSelectionValidator::check(self::delivery('WarehouseDoors'), self::MODULE, null, null, [], new CartMetrics(1, false, 1))->applies());
    }
}
