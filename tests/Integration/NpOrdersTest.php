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
        self::assertNull(NpOrders::shipment(self::waybill(['api' => null, 'number' => null]), null), 'a waybill being created at NP has no number yet');
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

    /** The cron runs every ten minutes: received parcels are filtered by the database, without their tracking JSON. */
    public function testReceivedSinceAsksTheDatabaseForOrderIdsOnly(): void
    {
        $asked = [];
        $entity = $this->createStub(\Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity::class);
        $entity->method('noLimit')->willReturnSelf();
        $entity->method('cols')->willReturnCallback(function (array $cols) use ($entity, &$asked) {
            $asked['cols'] = $cols;

            return $entity;
        });
        $entity->method('find')->willReturnCallback(function (array $filter) use (&$asked) {
            $asked['filter'] = $filter;

            // One column: the core entity returns the values themselves, not rows.
            return ['7', '7', '8'];
        });
        $factory = $this->createStub(\Okay\Core\EntityFactory::class);
        $factory->method('get')->willReturn($entity);

        self::assertSame([7, 8], new NpOrders($factory)->receivedSince('2026-11-01 00:00:00'));
        self::assertSame(['order_id'], $asked['cols']);
        self::assertSame(['received_since' => '2026-11-01 00:00:00'], $asked['filter']);
    }

    /** Messaging polls unfinished parcels on every cron run: the tracking JSON and audit are not read. */
    public function testShipmentsReadOnlyTheColumnsTheyUse(): void
    {
        /** @var \ArrayObject<string, array<mixed>> $asked */
        $asked = new \ArrayObject();
        $entity = function (string $class) use ($asked) {
            $entity = $this->createStub($class);
            // mappedBy() is final in the core entity: it runs for real and returns the stub.
            $entity->method('noLimit')->willReturnSelf();
            $entity->method('cols')->willReturnCallback(function (array $cols) use ($entity, $class, $asked) {
                $asked[$class] = $cols;

                return $entity;
            });
            $entity->method('find')->willReturn($class === \Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity::class ? [self::waybill()] : []);

            return $entity;
        };
        $waybills = $entity(\Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity::class);
        $deliveries = $entity(\Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity::class);
        $factory = $this->createStub(\Okay\Core\EntityFactory::class);
        $factory->method('get')->willReturnCallback(static fn(string $class) => $class === \Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity::class ? $waybills : $deliveries);
        $orders = new NpOrders($factory);

        foreach (['unfinished' => [], 'shipments' => [[990101]]] as $method => $args) {
            $asked->exchangeArray([]);
            self::assertArrayHasKey(990101, $orders->$method(...$args), $method);
            self::assertSame(['order_id', 'number', 'status_code', 'status_at', 'services'], $asked[\Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity::class] ?? null, $method);
            self::assertSame(['order_id', 'cod'], $asked[\Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity::class] ?? null, $method);
        }
    }
}
