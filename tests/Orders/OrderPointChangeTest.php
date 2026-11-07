<?php

namespace Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderPointChange;
use PHPUnit\Framework\TestCase;

class OrderPointChangeTest extends TestCase
{
    private const int MODULE = 74;

    private static function points(): object
    {
        return (object)['id' => 4, 'module_id' => self::MODULE, 'settings' => ['service_type' => 'WarehouseWarehouse']];
    }

    private static function address(): object
    {
        return (object)['id' => 5, 'module_id' => self::MODULE, 'settings' => ['service_type' => 'WarehouseDoors']];
    }

    private static function groupRows(): array
    {
        return [(object)['id' => 1, 'name' => 'Відділення', 'enabled' => 1, 'position' => 1, 'categories' => '{"PostBranch":true}']];
    }

    private static function settlement(): object
    {
        return (object)['id' => 118064, 'name_uk' => 'місто Київ', 'district_uk' => null, 'area_uk' => 'Київська область'];
    }

    private static function division(array $override = []): object
    {
        return (object)($override + [
            'id' => 7162, 'settlement_id' => 118064, 'number' => '11/1', 'category' => 'PostBranch', 'status' => 'Working', 'prohibited_issuance' => 0,
            'name_uk' => 'Відділення №1: вул. Пирогівський шлях, 135', 'max_weight' => '30.00', 'max_declared_cost' => '100000.00',
        ]);
    }

    private static function select(object $delivery, ?object $division, array $input = [], ?object $waybill = null, float $weight = 2.0): array
    {
        return OrderPointChange::select($delivery, self::MODULE, self::settlement(), $division, self::groupRows(), new CartMetrics($weight, false, 1000), $waybill, $input + ['settlement_id' => '118064'], false);
    }

    public function testPointIsChosen(): void
    {
        $result = self::select(self::points(), self::division());

        self::assertNull($result['error']);
        self::assertNull($result['warning']);
        self::assertSame(7162, $result['selection']->division->id);
    }

    public function testLockedByWaybill(): void
    {
        $result = self::select(self::points(), self::division(), [], (object)['api' => 'old', 'number' => '20450000000001']);

        self::assertSame('order_point_locked', $result['error']);
        self::assertNull($result['selection']);
    }

    public function testUnfitPointIsSavedWithWarning(): void
    {
        $result = self::select(self::points(), self::division(), [], null, 40.0);

        self::assertNull($result['error']);
        self::assertSame('error_point_too_heavy', $result['warning']);
        self::assertSame(7162, $result['selection']->division->id);
    }

    public function testClosedPointIsRefused(): void
    {
        self::assertSame('error_point_unavailable', self::select(self::points(), self::division(['status' => 'NotWorking']))['error']);
    }

    public function testPointOfOtherSettlementIsRefused(): void
    {
        self::assertSame('error_point', self::select(self::points(), self::division(['settlement_id' => 1]))['error']);
    }

    public function testAddressNeedsHouse(): void
    {
        self::assertSame('error_house', self::select(self::address(), null, ['street' => 'вул. Хрещатик', 'house' => ''])['error']);
    }

    public function testAddressIsChosen(): void
    {
        $result = self::select(self::address(), null, ['street' => 'вул. Хрещатик', 'house' => '22', 'flat' => '5', 'entrance' => '2', 'note' => 'код 15']);

        self::assertNull($result['error']);
        self::assertSame('22', $result['selection']->address->house);
        self::assertSame('2', $result['selection']->address->entrance);
    }

    public function testForeignDeliveryIsRefused(): void
    {
        self::assertSame('order_point_foreign', self::select((object)['id' => 9, 'module_id' => 12, 'settings' => []], self::division())['error']);
    }

    public function testOrderWithoutSnapshotCanGetOne(): void
    {
        self::assertNotNull(self::select(self::points(), self::division())['selection']);
    }
}
