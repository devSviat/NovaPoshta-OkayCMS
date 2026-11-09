<?php

namespace Modules\Sviat\NovaPoshta\Legacy;

use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyMigrationView;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReport;
use PHPUnit\Framework\TestCase;

class LegacyMigrationViewTest extends TestCase
{
    private const array STEPS = ['orders', 'waybills', 'payments', 'volumes', 'settings'];
    private const array COUNTS = ['orders' => 3, 'waybills' => 2, 'payments' => 1, 'volumes' => 0];

    private static function state(?string $status, int $step = 0, ?LegacyReport $report = null): array
    {
        return ['status' => $status, 'step' => $step, 'cursor' => 0, 'report' => ($report ?? new LegacyReport())->toArray(), 'requested_at' => null, 'started_at' => null, 'finished_at' => $status === 'done' ? '2026-11-08 14:32:00' : null];
    }

    public function testHiddenWhenNothingOldAndNeverRun(): void
    {
        self::assertNull(LegacyMigrationView::vars(self::state(null), self::COUNTS, [], [], self::STEPS));
    }

    public function testHiddenOnceDoneAndTheOldModulesAreGone(): void
    {
        self::assertNull(LegacyMigrationView::vars(self::state('done', 6), self::COUNTS, [], [], self::STEPS), 'a one-time move leaves no card behind');
        self::assertNotNull(LegacyMigrationView::vars(self::state('done', 6), self::COUNTS, ['OkayCMS/NovaposhtaCost'], [], self::STEPS));
    }

    public function testBeforeRunShowsPreview(): void
    {
        $vars = LegacyMigrationView::vars(self::state(null), self::COUNTS, ['OkayCMS/NovaposhtaCost'], [], self::STEPS);

        self::assertSame('before', $vars['phase']);
        self::assertSame(3, $vars['preview']['orders']);
        self::assertSame(2, $vars['preview']['waybills']);
        self::assertSame([], $vars['switch'], 'what to switch only after a run');
    }

    public function testRunningShowsStep(): void
    {
        $vars = LegacyMigrationView::vars(self::state('running', 1), self::COUNTS, ['OkayCMS/NovaposhtaCost'], [], self::STEPS);

        self::assertSame('running', $vars['phase']);
        self::assertSame('waybills', $vars['currentStep']);
        self::assertSame(2, $vars['stepNumber']);
    }

    public function testDoneShowsTotalsAndDeliveriesToSwitch(): void
    {
        $report = new LegacyReport();
        $report->add('orders', 'done');
        $report->add('orders', 'done');
        $report->add('orders', 'existed');
        $report->add('waybills', 'done');
        $report->fail('orders', 101, 'legacy_fail_no_point', 'Відділення №5');
        $report->fail('waybills', 102, 'legacy_fail_deleted', '20450000000001');
        $deliveries = [(object)['id' => 3, 'name' => 'Нова Пошта', 'service_type' => 'WarehouseWarehouse'], (object)['id' => 1, 'name' => 'Кур\'єр', 'service_type' => 'WarehouseDoors']];
        $vars = LegacyMigrationView::vars(self::state('done', 6, $report), self::COUNTS, ['OkayCMS/NovaposhtaCost'], $deliveries, self::STEPS);

        self::assertSame('done', $vars['phase']);
        self::assertSame(3, $vars['orders'], 'moved now or by an earlier run');
        self::assertSame(1, $vars['waybills']);
        self::assertSame(2, $vars['failed']);
        self::assertSame([['id' => 3, 'name' => 'Нова Пошта'], ['id' => 1, 'name' => 'Кур\'єр']], $vars['switch']);
        self::assertArrayNotHasKey('fails', $vars, 'the report is counts only');
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
        self::assertTrue(LegacyMigrationView::vars(self::state('done', 6), self::COUNTS, ['OkayCMS/NovaposhtaCost'], [], self::STEPS)['canStart']);
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
