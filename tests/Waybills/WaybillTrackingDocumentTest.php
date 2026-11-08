<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillTrackingDocument;
use PHPUnit\Framework\TestCase;

/**
 * A refresh from the card (old getStatusDocuments) writes the status; its date must belong to that
 * status: Checkbox counts receipts from it, Messaging the days a parcel waits at the branch.
 */
class WaybillTrackingDocumentTest extends TestCase
{
    private string $zone;

    protected function setUp(): void
    {
        $this->zone = date_default_timezone_get();
        date_default_timezone_set('Europe/Kyiv');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->zone);
    }

    /** NP gives Kyiv time; the shop stores its own (CI runs in UTC). */
    public function testScanDateIsConvertedToTheShopTimeZone(): void
    {
        date_default_timezone_set('UTC');

        self::assertSame('2026-11-07 13:01:00', WaybillTrackingDocument::scanDate('15:01 07.11.2026'));
    }

    public function testNewStatusTakesTheDateOfItsScan(): void
    {
        $columns = WaybillTrackingDocument::statusColumns(['StatusCode' => '9', 'Status' => 'Отримано', 'DateScan' => '15:01 07.11.2026'], (object)['status_code' => 7, 'status_at' => '2026-11-05 10:00:00']);

        self::assertSame(9, $columns['status_code']);
        self::assertSame('Отримано', $columns['status_text']);
        self::assertSame('2026-11-07 15:01:00', $columns['status_at']);
    }

    public function testNewStatusWithoutScanDateDropsThePreviousDate(): void
    {
        $columns = WaybillTrackingDocument::statusColumns(['StatusCode' => '9', 'DateScan' => ''], (object)['status_code' => 7, 'status_at' => '2026-11-05 10:00:00']);

        self::assertArrayHasKey('status_at', $columns);
        self::assertNull($columns['status_at'], 'the arrival date is not the date it was received');
    }

    public function testSameStatusKeepsTheDateTheTrackerWrote(): void
    {
        $columns = WaybillTrackingDocument::statusColumns(['StatusCode' => '7', 'DateScan' => '09:00 06.11.2026'], (object)['status_code' => 7, 'status_at' => '2026-11-05 10:00:00']);

        self::assertArrayNotHasKey('status_at', $columns);
    }

    public function testFirstStatusOfANewWaybill(): void
    {
        self::assertSame('2026-11-07 18:58:00', WaybillTrackingDocument::statusColumns(['StatusCode' => '1', 'DateScan' => '18:58 07.11.2026'], null)['status_at']);
    }

    public function testNoStatusWritesNothing(): void
    {
        self::assertSame([], WaybillTrackingDocument::statusColumns(['StatusCode' => '3', 'DateScan' => '18:58 07.11.2026'], (object)['status_code' => 1]));
        self::assertSame([], WaybillTrackingDocument::statusColumns([], null));
    }

    public function testUnreadableScanDate(): void
    {
        self::assertNull(WaybillTrackingDocument::statusColumns(['StatusCode' => '7', 'DateScan' => 'вчора'], (object)['status_code' => 4])['status_at']);
    }
}
