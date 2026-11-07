<?php

namespace Modules\Sviat\NovaPoshta\Legacy;

require_once __DIR__ . '/../Support/InMemoryState.php';

use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyBudget;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyMigration;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReport;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyStep;
use PHPUnit\Framework\TestCase;

class LegacyMigrationTest extends TestCase
{
    /** A step over ids 1..$last that takes one call per row and stops when calls run out. */
    private static function step(string $name, int $last, array &$seen): LegacyStep
    {
        $record = static function (string $row) use (&$seen): void {
            $seen[] = $row;
        };

        return new class($name, $last, $record) implements LegacyStep {
            public function __construct(private string $n, private int $last, private \Closure $record)
            {
            }

            public function name(): string
            {
                return $this->n;
            }

            public function run(int $cursor, LegacyBudget $budget, LegacyReport $report): int
            {
                for ($id = $cursor + 1; $id <= $this->last; $id++) {
                    if (!$budget->takeCall()) {
                        return $cursor;
                    }
                    ($this->record)($this->n . $id);
                    $report->add($this->n, 'done');
                    $cursor = $id;
                }

                return self::DONE;
            }
        };
    }

    public function testResumesFromCursor(): void
    {
        $seen = [];
        $state = new InMemoryState();
        $migration = new LegacyMigration($state, self::step('orders', 5, $seen), self::step('waybills', 1, $seen));

        self::assertTrue($migration->request());
        $migration->tick(new LegacyBudget(PHP_INT_MAX, 3));
        self::assertSame(['orders1', 'orders2', 'orders3'], $seen);
        self::assertSame('running', $migration->state()['status']);

        $migration->tick(new LegacyBudget(PHP_INT_MAX, 10));
        self::assertSame(['orders1', 'orders2', 'orders3', 'orders4', 'orders5', 'waybills1'], $seen, 'no row twice');
        self::assertSame(5, LegacyReport::fromArray($migration->state()['report'])->counts('orders')['done']);
    }

    public function testDoneAfterLastStep(): void
    {
        $seen = [];
        $migration = new LegacyMigration(new InMemoryState(), self::step('orders', 1, $seen), self::step('waybills', 0, $seen));
        $migration->request();
        $migration->tick(new LegacyBudget(PHP_INT_MAX, 10));

        $state = $migration->state();
        self::assertSame('done', $state['status']);
        self::assertNotNull($state['finished_at']);

        $migration->tick(new LegacyBudget(PHP_INT_MAX, 10));
        self::assertSame(['orders1'], $seen, 'a finished run does nothing more');
    }

    public function testRequestIgnoredWhileRunning(): void
    {
        $seen = [];
        $migration = new LegacyMigration(new InMemoryState(), self::step('orders', 5, $seen));
        self::assertTrue($migration->request());
        self::assertFalse($migration->request(), 'queued');
        $migration->tick(new LegacyBudget(PHP_INT_MAX, 1));
        self::assertFalse($migration->request(), 'running');
        $migration->tick(new LegacyBudget(PHP_INT_MAX, 10));
        self::assertTrue($migration->request(), 'done → may run again');
        self::assertSame('queued', $migration->state()['status']);
        self::assertSame([], LegacyReport::fromArray($migration->state()['report'])->fails());
    }

    public function testNothingBeforeRequest(): void
    {
        $seen = [];
        $migration = new LegacyMigration(new InMemoryState(), self::step('orders', 5, $seen));
        $migration->tick(new LegacyBudget(PHP_INT_MAX, 10));

        self::assertSame([], $seen);
        self::assertNull($migration->state()['status']);
    }

    public function testFailsAreCapped(): void
    {
        $report = new LegacyReport();
        for ($i = 1; $i <= 250; $i++) {
            $report->fail('orders', $i, 'legacy_fail_no_point');
        }
        $back = LegacyReport::fromArray(json_decode(json_encode($report->toArray()), true));

        self::assertCount(200, $back->fails());
        self::assertSame(50, $back->moreFails());
        self::assertSame(250, $back->counts('orders')['failed']);
    }
}
