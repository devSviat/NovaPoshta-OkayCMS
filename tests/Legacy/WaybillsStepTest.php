<?php

namespace Modules\Sviat\NovaPoshta\Legacy;

require_once __DIR__ . '/../Support/FakeLegacyData.php';
require_once __DIR__ . '/../Support/FakeLegacyOrders.php';

use Modules\Sviat\NovaPoshta\Support\FakeLegacyData;
use Modules\Sviat\NovaPoshta\Support\FakeLegacyOrders;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyBudget;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReport;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\Steps\WaybillsStep;
use PHPUnit\Framework\TestCase;

class WaybillsStepTest extends TestCase
{
    private FakeLegacyData $old;
    private FakeLegacyOrders $orders;

    private string $zone;

    protected function tearDown(): void
    {
        date_default_timezone_set($this->zone);
    }

    protected function setUp(): void
    {
        // DateScan від НП — київський час; очікування нижче в тій самій зоні.
        $this->zone = date_default_timezone_get();
        date_default_timezone_set('Europe/Kyiv');
        $this->old = new FakeLegacyData();
        $this->orders = new FakeLegacyOrders();
        foreach ([201, 202, 203, 204] as $id) {
            $this->orders->orders[$id] = (object)['id' => $id, 'delivery_id' => 3, 'delivery_price' => 0, 'payment_method_id' => 18];
        }
    }

    private static function row(int $id, int $orderId, array $over = []): object
    {
        return (object)($over + ['id' => $id, 'order_id' => $orderId, 'int_doc_number' => '20450000000045', 'ref_id' => 'ref-1', 'status_code' => '', 'tracking_update_at' => '2026-11-01 10:00:00', 'tracking_response' => null]);
    }

    private function migrate(LegacyReport $report): int
    {
        return new WaybillsStep($this->old, $this->orders)->run(0, new LegacyBudget(PHP_INT_MAX, 0), $report);
    }

    public function testTrackingDocumentIsCarried(): void
    {
        $doc = ['Number' => '20450000000045', 'StatusCode' => '9', 'Status' => 'Відправлення отримано', 'ScheduledDeliveryDate' => '03-11-2026 13:00:00', 'Ref' => 'doc-ref'];
        $this->old->tracking = [self::row(1, 201, ['status_code' => '9', 'tracking_response' => json_encode($doc)])];
        $report = new LegacyReport();

        self::assertSame(LegacyStep::DONE, $this->migrate($report));
        $w = $this->orders->waybills[201];
        self::assertSame('20450000000045', $w['number']);
        self::assertSame('ref-1', $w['ref']);
        self::assertSame('old', $w['api'], 'created by the shop: deleting goes through NP');
        self::assertSame(9, $w['status_code']);
        self::assertSame('Відправлення отримано', $w['status_text']);
        self::assertSame('2026-11-03 13:00:00', $w['scheduled_at']);
        self::assertSame('2026-11-01 10:00:00', $w['tracked_at']);
        self::assertSame('2026-11-01 10:00:00', $w['tracking']['_fetched_at']);
        self::assertSame('9', $w['tracking']['StatusCode']);
        self::assertSame(['done' => 1, 'existed' => 0, 'failed' => 0], $report->counts('waybills'));
    }

    /** status_at на кожному шляху означає дату статусу в НП; час оновлення старого модуля — лише запасний варіант. */
    public function testStatusDateIsTheScanDate(): void
    {
        $doc = ['Number' => '20450000000045', 'StatusCode' => '9', 'Status' => 'Відправлення отримано', 'DateScan' => '12:30 02.11.2026'];
        $this->old->tracking = [self::row(1, 201, ['status_code' => '9', 'tracking_response' => json_encode($doc)])];
        $this->migrate(new LegacyReport());

        self::assertSame('2026-11-02 12:30:00', $this->orders->waybills[201]['status_at']);
    }

    public function testStatusDateFallsBackToTheOldRefreshTime(): void
    {
        $doc = ['Number' => '20450000000045', 'StatusCode' => '9', 'DateScan' => ''];
        $this->old->tracking = [self::row(1, 201, ['status_code' => '9', 'tracking_response' => json_encode($doc)])];
        $this->migrate(new LegacyReport());

        self::assertSame('2026-11-01 10:00:00', $this->orders->waybills[201]['status_at']);
    }

    /**
     * Одна накладна — одне замовлення: номер, який старий модуль тримав на двох замовленнях, іде лише до першого, з
     * причиною.
     */
    public function testNumberOfAnotherOrderIsFailed(): void
    {
        $this->old->tracking = [self::row(1, 201), self::row(2, 202)];
        $report = new LegacyReport();
        $this->migrate($report);

        self::assertArrayHasKey(201, $this->orders->waybills);
        self::assertArrayNotHasKey(202, $this->orders->waybills);
        self::assertSame(['done' => 1, 'existed' => 0, 'failed' => 1], $report->counts('waybills'));
        self::assertSame(['step' => 'waybills', 'order' => 202, 'key' => 'legacy_fail_number_taken', 'name' => '20450000000045', 'record' => null], $report->fails()[0] ?? null);
    }

    /** Трекінг відлічує від неї свої 60 днів і 30-денне «не знайдено»: перенесення — не день народження накладної. */
    public function testCreatedDateIsTheOldOne(): void
    {
        $this->old->tracking = [self::row(1, 201, ['created_at' => '2025-03-01 10:00:00']), self::row(2, 202, ['int_doc_number' => '20450000000046', 'created_at' => null])];
        $this->migrate(new LegacyReport());

        self::assertSame('2025-03-01 10:00:00', $this->orders->waybills[201]['created_at']);
        self::assertNull($this->orders->waybills[202]['created_at'], 'unknown: the store puts the time of the move');
    }

    /** Старий трекер зберігав код у власній колонці; відповіді може вже не бути. */
    public function testStatusFromTheOldRowWithoutDocument(): void
    {
        $this->old->tracking = [self::row(1, 201, ['status_code' => '9', 'tracking_response' => null])];
        $this->migrate(new LegacyReport());

        self::assertSame(9, $this->orders->waybills[201]['status_code']);
        self::assertSame('2026-11-01 10:00:00', $this->orders->waybills[201]['status_at']);
    }

    /**
     * getStatusDocuments несе RefEW, а не Ref: Ref там не доводить, що накладну створив магазин, тож вона лишається
     * прив'язаною й ніколи не видаляється в НП.
     */
    public function testRefFromTheTrackingAnswerDoesNotMakeItOurs(): void
    {
        $doc = ['Number' => '20450000000046', 'StatusCode' => '9', 'Ref' => 'doc-ref'];
        $this->old->tracking = [self::row(1, 203, ['ref_id' => '', 'tracking_response' => json_encode($doc)])];
        $this->migrate(new LegacyReport());

        $w = $this->orders->waybills[203];
        self::assertNull($w['ref']);
        self::assertSame('attached', $w['api']);
    }

    public function testBrokenJsonKeepsNumber(): void
    {
        $this->old->tracking = [self::row(1, 202, ['ref_id' => '', 'tracking_response' => '{oops'])];
        $this->migrate(new LegacyReport());

        $w = $this->orders->waybills[202];
        self::assertSame('20450000000045', $w['number']);
        self::assertNull($w['ref']);
        self::assertSame('attached', $w['api'], 'no Ref: NP cannot be asked to delete it');
        self::assertNull($w['tracking']);
        self::assertNull($w['status_code']);
    }

    public function testDeletedAtNpIsSkipped(): void
    {
        $this->old->tracking = [self::row(1, 203, ['status_code' => '2', 'tracking_response' => json_encode(['StatusCode' => '2'])])];
        $report = new LegacyReport();
        $this->migrate($report);

        self::assertSame([], $this->orders->waybills);
        self::assertSame('legacy_fail_deleted', $report->fails()[0]['key']);
    }

    public function testOrderWithOurWaybillIsSkipped(): void
    {
        $this->orders->waybills[204] = ['number' => '20450000000099'];
        $this->old->tracking = [self::row(1, 204)];
        $report = new LegacyReport();
        $this->migrate($report);

        self::assertSame('20450000000099', $this->orders->waybills[204]['number']);
        self::assertSame(1, $report->counts('waybills')['existed']);
    }

    public function testBadNumberIsFailed(): void
    {
        $this->old->tracking = [self::row(1, 201, ['int_doc_number' => '12-34']), self::row(2, 999)];
        $report = new LegacyReport();
        $this->migrate($report);

        self::assertSame([], $this->orders->waybills);
        self::assertSame(['legacy_fail_bad_number', 'legacy_fail_no_order'], array_column($report->fails(), 'key'));
        self::assertSame([null, 2], array_column($report->fails(), 'record'));
    }
}
