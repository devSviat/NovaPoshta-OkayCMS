<?php

namespace Modules\Sviat\NovaPoshta\Legacy;

require_once __DIR__ . '/../Support/FakeLegacyPlaces.php';
require_once __DIR__ . '/../Support/FakeLegacyData.php';
require_once __DIR__ . '/../Support/FakeLegacyOrders.php';

use Modules\Sviat\NovaPoshta\Support\FakeLegacyData;
use Modules\Sviat\NovaPoshta\Support\FakeLegacyOrders;
use Modules\Sviat\NovaPoshta\Support\FakeLegacyPlaces;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyAddressMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyBudget;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyCityMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyDivisionMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReport;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\Steps\OrdersStep;
use PHPUnit\Framework\TestCase;

class OrdersStepTest extends TestCase
{
    private FakeLegacyData $old;
    private FakeLegacyOrders $orders;
    private FakeLegacyPlaces $places;

    protected function setUp(): void
    {
        $this->old = new FakeLegacyData();
        $this->orders = new FakeLegacyOrders();
        $this->places = new FakeLegacyPlaces();
        $this->places->settlements = [
            (object)['id' => 1, 'name_uk' => 'місто Київ', 'area_uk' => 'Київська область', 'district_uk' => null, 'has_pickup' => 1],
            (object)['id' => 2, 'name_uk' => 'село Липове', 'area_uk' => 'Полтавська область', 'district_uk' => 'Лубенський район', 'has_pickup' => 0],
        ];
        $this->places->divisions = [
            (object)['id' => 10, 'settlement_id' => 1, 'number' => '11/13', 'category' => 'Branch', 'name_uk' => 'Відділення №13: вул. Дорогожицька, 4', 'address_uk' => null],
        ];
        $this->old->cities = ['kyiv-ref' => 'Київ'];
        $this->old->warehouses = [
            'w13' => (object)['ref' => 'w13', 'city_ref' => 'kyiv-ref', 'number' => 13, 'name' => 'Відділення №13 (до 30 кг): вул. Дорогожицька, 4'],
            'w5' => (object)['ref' => 'w5', 'city_ref' => 'kyiv-ref', 'number' => 5, 'name' => 'Відділення №5: вул. Закрита, 1'],
        ];
        foreach ([101, 102, 103, 104, 105] as $id) {
            $this->orders->orders[$id] = (object)['id' => $id, 'delivery_id' => 3, 'delivery_price' => 80.0, 'payment_method_id' => 18];
        }
    }

    private static function row(int $id, ?int $orderId, array $over = []): object
    {
        return (object)($over + [
            'id' => $id, 'order_id' => $orderId, 'city_id' => '', 'warehouse_id' => '', 'redelivery' => null, 'city_name' => '', 'area_name' => '',
            'region_name' => '', 'street' => '', 'house' => '', 'apartment' => '', 'control_payment' => null, 'recipient_address_note' => null,
            'door_delivery' => null, 'lifting_floor' => null,
        ]);
    }

    private function migrate(LegacyReport $report, ?LegacyBudget $budget = null, int $cursor = 0): int
    {
        $step = new OrdersStep($this->old, $this->orders, $this->places, new LegacyCityMatcher($this->places), new LegacyDivisionMatcher($this->places), new LegacyAddressMatcher($this->places));

        return $step->run($cursor, $budget ?? new LegacyBudget(PHP_INT_MAX, 5), $report);
    }

    public function testPointIsMatched(): void
    {
        $this->old->delivery = [self::row(1, 101, ['city_id' => 'kyiv-ref', 'warehouse_id' => 'w13', 'redelivery' => 1])];
        $report = new LegacyReport();

        self::assertSame(LegacyStep::DONE, $this->migrate($report));
        $row = $this->orders->rows[101];
        self::assertSame('points', $row['mode']);
        self::assertSame(10, $row['division_id']);
        self::assertSame('11/13', $row['division_number']);
        self::assertSame('Відділення №13', $row['division_name']);
        self::assertSame('вул. Дорогожицька, 4', $row['division_address']);
        self::assertSame('місто Київ', $row['settlement_name']);
        self::assertSame(1, $row['cod']);
        self::assertSame('legacy', $row['price_source']);
        self::assertSame('migrated', $row['legacy']);
        self::assertSame(3, $row['delivery_id']);
        self::assertSame(['done' => 1, 'existed' => 0, 'failed' => 0], $report->counts('orders'));
    }

    /** Запис у базу не вдався: перенесення не рахує замовлення ні перенесеним, ні таким, що вже було. */
    public function testFailedWriteIsReportedAsFailed(): void
    {
        $this->old->delivery = [self::row(1, 101, ['city_id' => 'kyiv-ref', 'warehouse_id' => 'w13'])];
        $this->orders->failWrites = true;
        $report = new LegacyReport();

        $this->migrate($report);

        self::assertSame(['done' => 0, 'existed' => 0, 'failed' => 1], $report->counts('orders'));
        self::assertSame('legacy_fail_write', $report->fails()[0]['key']);
    }

    public function testGonePointIsKeptWithNames(): void
    {
        $this->old->delivery = [self::row(1, 101, ['city_id' => 'kyiv-ref', 'warehouse_id' => 'w5']), self::row(2, 102, ['city_id' => 'x', 'warehouse_id' => 'unknown-ref'])];
        $report = new LegacyReport();
        $this->migrate($report);

        self::assertNull($this->orders->rows[101]['division_id']);
        self::assertSame('Відділення №5: вул. Закрита, 1', $this->orders->rows[101]['division_name']);
        self::assertSame('місто Київ', $this->orders->rows[101]['settlement_name'], 'the city did match');
        self::assertSame('unmatched', $this->orders->rows[101]['legacy']);
        self::assertSame('unmatched', $this->orders->rows[102]['legacy']);
        self::assertSame(['done' => 0, 'existed' => 0, 'failed' => 2], $report->counts('orders'));
        self::assertSame('legacy_fail_no_point', $report->fails()[0]['key']);
        self::assertSame(101, $report->fails()[0]['order']);
    }

    public function testAddressWithLiftingFloor(): void
    {
        $this->old->delivery = [self::row(1, 103, ['city_id' => 'sett-ref', 'city_name' => 'Липове', 'area_name' => 'Полтавська', 'region_name' => 'Лубенський', 'street' => 'вул. Шкільна', 'house' => '5', 'apartment' => '12', 'recipient_address_note' => 'дзвонити', 'door_delivery' => 1, 'lifting_floor' => '4'])];
        $report = new LegacyReport();
        $this->migrate($report);

        $row = $this->orders->rows[103];
        self::assertSame('address', $row['mode']);
        self::assertSame(2, $row['settlement_id']);
        self::assertSame('село Липове', $row['settlement_name']);
        self::assertSame('вул. Шкільна', $row['street_name']);
        self::assertSame('12', $row['flat']);
        self::assertSame(1, $row['lifting']);
        self::assertSame(4, $row['floor']);
        self::assertSame('дзвонити', $row['address_note']);
        self::assertSame('migrated', $row['legacy']);
    }

    /** Велике місто має сіл-тезок, а старі рядки адрес не містять області: вирішує City Ref. */
    public function testAddressCityIsMatchedByItsRef(): void
    {
        $this->places->settlements[] = (object)['id' => 4, 'name_uk' => 'село Київ', 'area_uk' => 'Миколаївська область', 'district_uk' => 'Вознесенський район', 'has_pickup' => 0];
        $this->places->settlements[] = (object)['id' => 5, 'name_uk' => 'село Липове', 'area_uk' => 'Сумська область', 'district_uk' => null, 'has_pickup' => 1];
        $this->places->cityRefs['lypove-ref'] = 5;
        $this->old->delivery = [
            self::row(1, 101, ['city_id' => 'kyiv-ref', 'city_name' => 'Київ', 'street' => 'вул. Хрещатик', 'house' => '1']),
            self::row(2, 102, ['city_id' => 'lypove-ref', 'city_name' => 'Липове', 'street' => 'вул. Шкільна', 'house' => '2']),
        ];
        $report = new LegacyReport();
        $this->migrate($report);

        self::assertSame(1, $this->orders->rows[101]['settlement_id']);
        self::assertSame('migrated', $this->orders->rows[101]['legacy']);
        self::assertSame(5, $this->orders->rows[102]['settlement_id'], 'a remembered City Ref wins over a namesake');
        self::assertSame(['done' => 2, 'existed' => 0, 'failed' => 0], $report->counts('orders'));
    }

    public function testAddressWaitsWhenTheCityRefNeedsNp(): void
    {
        $this->places->settlements[] = (object)['id' => 3, 'name_uk' => 'село Липове', 'area_uk' => 'Сумська область', 'district_uk' => null, 'has_pickup' => 1];
        $this->places->settlements[1]->has_pickup = 1;
        $this->old->delivery = [self::row(1, 103, ['city_id' => 'far-ref', 'city_name' => 'Липове', 'street' => 'вул. Шкільна', 'house' => '5'])];

        self::assertSame(0, $this->migrate(new LegacyReport(), new LegacyBudget(PHP_INT_MAX, 0)));
        self::assertArrayNotHasKey(103, $this->orders->rows);
    }

    public function testOrderWithOurRowIsSkipped(): void
    {
        $this->orders->rows[104] = ['mode' => 'points'];
        $this->old->delivery = [self::row(1, 104, ['city_id' => 'kyiv-ref', 'warehouse_id' => 'w13'])];
        $report = new LegacyReport();
        $this->migrate($report);

        self::assertSame(['mode' => 'points'], $this->orders->rows[104], 'untouched');
        self::assertSame(['done' => 0, 'existed' => 1, 'failed' => 0], $report->counts('orders'));
    }

    public function testRowWithoutOrderIsSkipped(): void
    {
        $this->old->delivery = [self::row(1, null, ['warehouse_id' => 'w13']), self::row(2, 999, ['warehouse_id' => 'w13'])];
        $report = new LegacyReport();
        $this->migrate($report);

        self::assertSame([], $this->orders->rows);
        self::assertSame(2, $report->counts('orders')['failed']);
        // У магазині немає такого замовлення: звіт називає старий запис, а не посилання в нікуди.
        self::assertSame(['step' => 'orders', 'order' => null, 'key' => 'legacy_fail_no_order_id', 'name' => null, 'record' => 1], $report->fails()[0]);
        self::assertSame(['step' => 'orders', 'order' => null, 'key' => 'legacy_fail_no_order', 'name' => '999', 'record' => 2], $report->fails()[1]);
    }

    public function testRowWithoutDataIsSkipped(): void
    {
        $this->old->delivery = [self::row(1, 105)];
        $report = new LegacyReport();
        $this->migrate($report);

        self::assertSame([], $this->orders->rows);
        self::assertSame('legacy_fail_no_data', $report->fails()[0]['key']);
    }

    public function testStopsBeforeRowThatWaitsForNp(): void
    {
        $this->old->cities['far-ref'] = 'Липове';
        $this->places->settlements[] = (object)['id' => 3, 'name_uk' => 'село Липове', 'area_uk' => 'Сумська область', 'district_uk' => null, 'has_pickup' => 1];
        $this->places->settlements[1]->has_pickup = 1;
        $this->old->warehouses['w1'] = (object)['ref' => 'w1', 'city_ref' => 'far-ref', 'number' => 1, 'name' => 'Відділення №1'];
        $this->old->delivery = [self::row(1, 101, ['city_id' => 'kyiv-ref', 'warehouse_id' => 'w13']), self::row(2, 102, ['city_id' => 'far-ref', 'warehouse_id' => 'w1'])];
        $report = new LegacyReport();

        self::assertSame(1, $this->migrate($report, new LegacyBudget(PHP_INT_MAX, 0)), 'cursor stays before the row that needs NP');
        self::assertArrayNotHasKey(102, $this->orders->rows);
    }
}
