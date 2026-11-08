<?php

namespace Modules\Sviat\NovaPoshta\Integration;

use Okay\Modules\Sviat\NovaPoshta\Integration\NpAddress;
use Okay\Modules\Sviat\NovaPoshta\Integration\NpOrders;
use Okay\Modules\Sviat\NovaPoshta\Integration\NpShipment;
use PHPUnit\Framework\TestCase;

class NpOrdersTest extends TestCase
{
    private static function waybill(array $values = []): object
    {
        return (object)($values + ['order_id' => 990101, 'number' => '20450000000001', 'api' => 'old', 'services' => null, 'status_code' => null, 'status_at' => null, 'tracked_at' => null]);
    }

    public function testShipmentSkipsRowsWithoutNumber(): void
    {
        self::assertNull(NpOrders::shipment(self::waybill(['number' => '']), null));
        self::assertNull(NpOrders::shipment(self::waybill(['api' => 'pending']), null), 'a waybill being created at NP has no number yet');
        self::assertInstanceOf(NpShipment::class, NpOrders::shipment(self::waybill(), null));
    }

    public function testShipmentCarriesNumberAndStatus(): void
    {
        $shipment = NpOrders::shipment(self::waybill(['status_code' => '4', 'status_at' => '2026-11-04 09:00:00']), null);

        self::assertSame(990101, $shipment->orderId);
        self::assertSame('20450000000001', $shipment->number);
        self::assertSame(4, $shipment->statusCode);
        self::assertSame('2026-11-04 09:00:00', $shipment->statusAt);
    }

    public function testArrivedAtIsStatusDateWhileArrived(): void
    {
        self::assertSame('2026-11-05 10:00:00', NpOrders::shipment(self::waybill(['status_code' => 7, 'status_at' => '2026-11-05 10:00:00']), null)->arrivedAt);
        self::assertSame('2026-11-05 10:00:00', NpOrders::shipment(self::waybill(['status_code' => 8, 'status_at' => '2026-11-05 10:00:00']), null)->arrivedAt);
        self::assertNull(NpOrders::shipment(self::waybill(['status_code' => 4, 'status_at' => '2026-11-05 10:00:00']), null)->arrivedAt);
        self::assertNull(NpOrders::shipment(self::waybill(), null)->statusCode);
    }

    public function testCodFromWaybillServices(): void
    {
        self::assertTrue(NpOrders::shipment(self::waybill(['services' => '{"cod":true,"codAmount":1200}']), null)->cod);
        self::assertFalse(NpOrders::shipment(self::waybill(['services' => '{"cod":false}']), (object)['cod' => 1])->cod, 'the manager took cash on delivery off the waybill');
    }

    public function testCodFallsBackToOrderSnapshot(): void
    {
        self::assertTrue(NpOrders::shipment(self::waybill(['services' => null]), (object)['cod' => 1])->cod, 'a migrated waybill keeps no services');
        self::assertTrue(NpOrders::shipment(self::waybill(['services' => '[]']), (object)['cod' => '1'])->cod);
        self::assertFalse(NpOrders::shipment(self::waybill(['services' => '[]']), (object)['cod' => 0])->cod);
        self::assertFalse(NpOrders::shipment(self::waybill(), null)->cod);
    }

    public function testReceivedAtUsesStatusDate(): void
    {
        self::assertSame('2026-11-01 08:00:00', NpOrders::receivedAt(self::waybill(['status_code' => 9, 'status_at' => '2026-11-01 08:00:00', 'tracked_at' => '2026-11-08 08:00:00', 'updated_at' => '2026-11-08 08:00:00'])));
        self::assertNull(NpOrders::receivedAt(self::waybill(['status_code' => 7, 'status_at' => '2026-11-01 08:00:00'])), 'only received (9) counts');
        self::assertNull(NpOrders::receivedAt(self::waybill(['status_code' => 9, 'number' => ''])));
    }

    public function testReceivedAtFallsBackToTrackedAt(): void
    {
        self::assertSame('2026-11-08 08:00:00', NpOrders::receivedAt(self::waybill(['status_code' => 9, 'status_at' => null, 'tracked_at' => '2026-11-08 08:00:00'])));
        self::assertNull(NpOrders::receivedAt(self::waybill(['status_code' => 9])), 'no date: never counted as new');
    }

    public function testReceivedSinceComparesDates(): void
    {
        $rows = [
            self::waybill(['order_id' => 1, 'status_code' => 9, 'status_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-11-08 00:00:00']),
            self::waybill(['order_id' => 2, 'status_code' => 9, 'status_at' => '2026-11-02 00:00:00']),
            self::waybill(['order_id' => 3, 'status_code' => 9, 'status_at' => '2026-11-01 00:00:00']),
        ];

        self::assertSame([2, 3], NpOrders::receivedFrom($rows, '2026-11-01 00:00:00'));
    }

    public function testAddressPoint(): void
    {
        $address = NpOrders::address((object)['mode' => 'point', 'settlement_name' => 'Вигадане', 'division_name' => 'Відділення №3', 'division_address' => 'вул. Тестова, 1']);

        self::assertEquals(new NpAddress('Вигадане', 'Відділення №3, вул. Тестова, 1'), $address);
        self::assertSame('Відділення №3', NpOrders::address((object)['mode' => 'point', 'settlement_name' => 'Вигадане', 'division_name' => 'Відділення №3', 'division_address' => ''])->line);
    }

    public function testAddressDoor(): void
    {
        $row = ['mode' => 'address', 'settlement_name' => 'Вигадане', 'street_name' => 'вул. Тестова', 'house' => '5', 'flat' => '12'];

        self::assertSame('вул. Тестова, 5, кв. 12', NpOrders::address((object)$row)->line);
        self::assertSame('вул. Тестова, 5', NpOrders::address((object)(['flat' => ''] + $row))->line);
        self::assertNull(NpOrders::address(null));
    }
}
