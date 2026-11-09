<?php

namespace Modules\Sviat\NovaPoshta\Legacy;

use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyTables;
use PHPUnit\Framework\TestCase;

class LegacyTablesTest extends TestCase
{
    public function testMissingTablesGiveNothing(): void
    {
        self::assertFalse(LegacyTables::present(['ok_orders'], 'ok_', '__okaycms__np_cost_delivery_data'), 'NovaposhtaCost already removed');
        self::assertTrue(LegacyTables::present(['ok_orders', 'ok_okaycms__np_cost_delivery_data'], 'ok_', '__okaycms__np_cost_delivery_data'));
    }

    public function testOnlyColumnsThatExistAreRead(): void
    {
        self::assertSame(['id', 'order_id', 'door_delivery'], LegacyTables::selectable(['id', 'order_id', 'door_delivery', 'city_id'], ['id', 'order_id', 'door_delivery', 'lifting_floor']));
    }

    public function testServiceTypeToMode(): void
    {
        self::assertSame(DeliveryMode::Points, LegacyTables::modeOf('WarehouseWarehouse'));
        self::assertSame(DeliveryMode::Points, LegacyTables::modeOf('DoorsWarehouse'));
        self::assertSame(DeliveryMode::Address, LegacyTables::modeOf('WarehouseDoors'));
        self::assertSame(DeliveryMode::Address, LegacyTables::modeOf('DoorsDoors'));
        self::assertNull(LegacyTables::modeOf(null));
        self::assertNull(LegacyTables::modeOf('Parcel'));
    }
}
