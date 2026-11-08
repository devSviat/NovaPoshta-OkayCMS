<?php

namespace Modules\Sviat\NovaPoshta\Legacy;

use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyMigrationView;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReport;
use PHPUnit\Framework\TestCase;

class LegacyMigrationViewTest extends TestCase
{
    private const array STEPS = ['orders', 'waybills', 'payments', 'volumes', 'popular', 'settings'];
    private const array COUNTS = ['orders' => 3, 'waybills' => 2, 'payments' => 1, 'volumes' => 0, 'popular' => 0];

    private static function state(?string $status, int $step = 0, ?LegacyReport $report = null): array
    {
        return ['status' => $status, 'step' => $step, 'cursor' => 0, 'report' => ($report ?? new LegacyReport())->toArray(), 'requested_at' => null, 'started_at' => null, 'finished_at' => $status === 'done' ? '2026-11-08 14:32:00' : null];
    }

    public function testHiddenWhenNothingOldAndNeverRun(): void
    {
        self::assertNull(LegacyMigrationView::vars(self::state(null), self::COUNTS, [], [], self::STEPS));
    }

    public function testBeforeRunShowsPreview(): void
    {
        $vars = LegacyMigrationView::vars(self::state(null), self::COUNTS, ['OkayCMS/NovaposhtaCost'], [], self::STEPS);

        self::assertSame('before', $vars['phase']);
        self::assertSame(['OkayCMS/NovaposhtaCost'], $vars['enabled']);
        self::assertSame(3, $vars['preview']['orders']);
        self::assertSame([], $vars['switch'], 'step 3 hint only after a run');
    }

    public function testRunningShowsStep(): void
    {
        $vars = LegacyMigrationView::vars(self::state('running', 1), self::COUNTS, ['OkayCMS/NovaposhtaCost'], [], self::STEPS);

        self::assertSame('running', $vars['phase']);
        self::assertSame('waybills', $vars['currentStep']);
    }

    public function testDoneShowsReportAndSwitchHints(): void
    {
        $report = new LegacyReport();
        $report->add('orders', 'done');
        $report->fail('orders', 101, 'legacy_fail_no_point', 'Відділення №5');
        $deliveries = [(object)['id' => 3, 'name' => 'Нова Пошта', 'service_type' => 'WarehouseWarehouse'], (object)['id' => 1, 'name' => 'Кур\'єр', 'service_type' => 'WarehouseDoors'], (object)['id' => 7, 'name' => 'Дивний', 'service_type' => null]];
        $vars = LegacyMigrationView::vars(self::state('done', 6, $report), self::COUNTS, [], $deliveries, self::STEPS);

        self::assertSame('done', $vars['phase']);
        self::assertSame(['done' => 1, 'existed' => 0, 'failed' => 1], $vars['rows']['orders']);
        self::assertSame(101, $vars['fails'][0]['order']);
        self::assertSame([['id' => 3, 'name' => 'Нова Пошта', 'mode' => 'points'], ['id' => 1, 'name' => 'Кур\'єр', 'mode' => 'address'], ['id' => 7, 'name' => 'Дивний', 'mode' => null]], $vars['switch']);
    }

    public function testBlockedUntilReadyAndStalledMayRestart(): void
    {
        $blocked = LegacyMigrationView::vars(self::state(null), self::COUNTS, ['OkayCMS/NovaposhtaCost'], [], self::STEPS, 'legacy_needs_dictionaries');
        self::assertSame('legacy_needs_dictionaries', $blocked['problem']);
        self::assertFalse($blocked['canStart']);

        $stalled = LegacyMigrationView::vars(self::state('running', 0), self::COUNTS, [], [], self::STEPS, null, true);
        self::assertTrue($stalled['canStart'], 'a run held for hours may be started again');
        self::assertTrue($stalled['stalled']);

        self::assertFalse(LegacyMigrationView::vars(self::state('running', 0), self::COUNTS, [], [], self::STEPS)['canStart']);
        self::assertTrue(LegacyMigrationView::vars(self::state('done', 6), self::COUNTS, [], [], self::STEPS)['canStart']);
    }

    public function testFinishedAtReadsLikeTheRestOfTheAdmin(): void
    {
        $vars = LegacyMigrationView::vars(self::state('done', 6), self::COUNTS, ['OkayCMS/NovaposhtaCost'], [], self::STEPS);

        self::assertSame('08.11.2026 14:32', $vars['finishedAt']);
    }

    public function testRecordSurvivesTheStoredReport(): void
    {
        $report = new \Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReport();
        $report->fail('orders', null, 'legacy_fail_no_order', '999', 12);

        self::assertSame(12, \Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReport::fromArray($report->toArray())->fails()[0]['record']);
    }
}
