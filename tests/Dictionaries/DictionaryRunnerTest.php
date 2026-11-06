<?php

namespace Modules\Sviat\NovaPoshta\Dictionaries;

require_once __DIR__ . '/../Support/InMemoryState.php';
require_once __DIR__ . '/../Support/RecordingSteps.php';

use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Modules\Sviat\NovaPoshta\Support\RecordingSteps;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Cdn\CdnVersions;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryRunner;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryStatus;
use PHPUnit\Framework\TestCase;

class DictionaryRunnerTest extends TestCase
{
    private const int DAY = 86400;
    private const int T0 = 1_793_900_000;

    private RecordingSteps $steps;
    private InMemoryState $state;
    private int $now = self::T0;

    protected function setUp(): void
    {
        $this->steps = new RecordingSteps();
        $this->state = new InMemoryState();
        $this->publish(base: self::T0 - 1000, deltaTills: [self::T0 - 3000, self::T0 - 2000, self::T0 - 1000], settlements: self::T0 - 500);
    }

    /** CDN state: a base at $base and consecutive deltas ending at each of $deltaTills. */
    private function publish(int $base, array $deltaTills, int $settlements): void
    {
        foreach (['uk', 'en'] as $lang) {
            $deltas = [];
            $from = $deltaTills[0] - self::DAY;
            foreach ($deltaTills as $till) {
                $deltas[] = ['from' => $from, 'till' => $till, 'url' => "d-{$lang}-{$till}"];
                $from = $till;
            }
            $this->steps->versions["divisions:{$lang}"] = new CdnVersions($base, "base-{$lang}-{$base}", $deltas);
            $this->steps->versions["settlements:{$lang}"] = new CdnVersions($settlements, "s-{$lang}-{$settlements}", []);
        }
    }

    private function runner(): DictionaryRunner
    {
        return new DictionaryRunner($this->steps, $this->state, fn(): int => $this->now);
    }

    private function dictStatus(): DictionaryStatus
    {
        return DictionaryStatus::load($this->state);
    }

    private function tick(): array
    {
        $this->steps->calls = [];
        $this->runner()->tick();

        return $this->steps->calls;
    }

    public function testFirstRunLoadsEverything(): void
    {
        self::assertSame([
            'fullSettlements s-uk-' . (self::T0 - 500) . ' -',
            'fullDivisions base-uk-' . (self::T0 - 1000) . ' -',
            'boost',
        ], $this->tick());

        $status = $this->dictStatus();
        self::assertTrue($status->lastRun['ok']);
        self::assertSame('full', $status->lastRun['kind']);
        self::assertSame(self::T0 - 1000, $status->divisionsTill);
        self::assertSame(self::T0 - 500, $status->settlementsTime);
        self::assertSame(self::T0, $status->boostAt);
        self::assertSame(['added' => 10, 'changed' => 0, 'deleted' => 0, 'unchanged' => 0], $status->lastRun['counts']['divisions']);
        self::assertNull($status->runningSince);
    }

    public function testNothingIsDueRightAfterARun(): void
    {
        $this->tick();
        $this->now += 3600;

        self::assertSame([], $this->tick());
    }

    public function testNextDayAppliesOnlyNewDeltasInOrder(): void
    {
        $this->tick();
        $this->now += self::DAY;
        $this->publish(base: self::T0 - 1000 + self::DAY, deltaTills: [self::T0 - 1000, self::T0 - 1000 + 40000, self::T0 - 1000 + self::DAY], settlements: self::T0 - 500);

        self::assertSame([
            'deltaDivisions d-uk-' . (self::T0 - 1000 + 40000) . ' -',
            'deltaDivisions d-uk-' . (self::T0 - 1000 + self::DAY) . ' -',
        ], $this->tick());
        self::assertSame('delta', $this->dictStatus()->lastRun['kind']);
        self::assertSame(self::T0 - 1000 + self::DAY, $this->dictStatus()->divisionsTill);
    }

    public function testBrokenChainLoadsTheBaseAgain(): void
    {
        $this->tick();
        $this->now += 10 * self::DAY;
        $base = self::T0 + 9 * self::DAY;
        $this->publish(base: $base, deltaTills: [$base - 2 * self::DAY, $base - self::DAY, $base], settlements: self::T0 - 500);

        self::assertSame(["fullDivisions base-uk-{$base} -", 'boost'], $this->tick());
        self::assertSame($base, $this->dictStatus()->divisionsTill);
    }

    public function testButtonRunsAtOnce(): void
    {
        $this->tick();
        $this->now += 600;
        $this->runner()->requestRun();
        self::assertNotNull($this->dictStatus()->requestedAt);

        $this->tick();

        self::assertNull($this->dictStatus()->requestedAt);
        self::assertSame(self::T0 + 600, $this->dictStatus()->lastRun['started']);
    }

    public function testFailureIsRecordedAndRetriedAfterAnHour(): void
    {
        $this->steps->failures['fullDivisions'] = NpApiException::fromNp(NpErrorKind::Unavailable, ['CDN timeout']);

        $this->tick();
        $status = $this->dictStatus();
        self::assertFalse($status->lastRun['ok']);
        self::assertStringContainsString('CDN timeout', $status->lastRun['error']);
        self::assertSame(self::T0 - 500, $status->settlementsTime, 'what succeeded is kept');
        self::assertNull($status->divisionsTill);
        self::assertNull($status->runningSince);

        unset($this->steps->failures['fullDivisions']);
        $this->now += 3000;
        self::assertSame([], $this->tick());
        $this->now += 700;
        self::assertContains('fullDivisions base-uk-' . (self::T0 - 1000) . ' -', $this->tick());
    }

    public function testMissingKeySkipsOnlyBoost(): void
    {
        $this->steps->failures['boost'] = NpApiException::fromNp(NpErrorKind::InvalidKey, ['API key is not set']);

        $this->tick();

        $status = $this->dictStatus();
        self::assertTrue($status->lastRun['ok']);
        self::assertNull($status->boostAt);
        self::assertNotNull($status->lastRun['boost_error']);
    }

    public function testBoostWeekly(): void
    {
        $this->tick();
        $this->now += 2 * self::DAY;
        self::assertNotContains('boost', $this->tick());
        $this->now += 6 * self::DAY;
        self::assertContains('boost', $this->tick());
    }

    public function testEnglishAddedToTheSiteReloadsWithEnglishNames(): void
    {
        $this->tick();
        $this->steps->english = true;
        $this->now += self::DAY;

        $calls = $this->tick();

        self::assertContains('fullSettlements s-uk-' . (self::T0 - 500) . ' s-en-' . (self::T0 - 500), $calls);
        self::assertContains('fullDivisions base-uk-' . (self::T0 - 1000) . ' base-en-' . (self::T0 - 1000), $calls);
    }

    public function testIncompleteDataIsRetriedAfterAnHourNotADay(): void
    {
        $this->tick();
        $status = $this->dictStatus();
        $status->divisionsTill = null;
        $status->save($this->state);

        $this->now += 3000;
        self::assertSame([], $this->tick());
        $this->now += 700;
        self::assertContains('fullDivisions base-uk-' . (self::T0 - 1000) . ' -', $this->tick());
    }

    public function testNoSecondRunWhileOneIsRunning(): void
    {
        $this->tick();
        $status = $this->dictStatus();
        $status->runningSince = $this->now + 2 * self::DAY - 60;
        $status->save($this->state);
        $this->now += 2 * self::DAY;
        $this->publish(base: self::T0 - 1000 + self::DAY, deltaTills: [self::T0 - 1000, self::T0 - 1000 + 40000, self::T0 - 1000 + self::DAY], settlements: self::T0 - 500);

        self::assertSame([], $this->tick(), 'the core lock is not reliable on stock');
    }

    public function testKilledRunIsRetriedAfterAnHourNotEveryTick(): void
    {
        $this->tick();
        $status = $this->dictStatus();
        $status->runningSince = $this->now + 2 * self::DAY - 3700;
        $status->save($this->state);
        $this->now += 2 * self::DAY;

        self::assertSame([], $this->tick());
        $killed = $this->dictStatus();
        self::assertNull($killed->runningSince);
        self::assertFalse($killed->lastRun['ok']);
        self::assertSame('interrupted', $killed->lastRun['error']);

        $this->now += 3600;
        $this->tick();
        self::assertTrue($this->dictStatus()->lastRun['ok']);
        self::assertSame($this->now, $this->dictStatus()->lastRun['started']);
    }

    public function testButtonPressedDuringARunIsKept(): void
    {
        $this->steps->onStep = function (): void {
            $this->runner()->requestRun();
            $this->steps->onStep = null;
        };

        $this->tick();

        self::assertNotNull($this->dictStatus()->requestedAt);
    }

    public function testStatusSurvivesBrokenJson(): void
    {
        $this->state->set('dict_status', '{broken');

        self::assertNull(DictionaryStatus::load($this->state)->lastRun);
    }
}
