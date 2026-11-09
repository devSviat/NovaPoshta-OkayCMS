<?php

namespace Modules\Sviat\NovaPoshta\Backend;

use Okay\Modules\Sviat\NovaPoshta\Backend\DictionaryStatusView;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DictionaryStatusViewTest extends TestCase
{
    private const int NOW = 1_900_000_000;

    private static function makeStatus(array $set = []): DictionaryStatus
    {
        $status = new DictionaryStatus();
        foreach ($set as $field => $value) {
            $status->$field = $value;
        }

        return $status;
    }

    private static function lastRun(bool $ok, int $finishedAgo, ?string $boostError = null): array
    {
        return ['started' => self::NOW - $finishedAgo - 60, 'finished' => self::NOW - $finishedAgo, 'ok' => $ok, 'kind' => 'full',
            'counts' => ['divisions' => ['added' => 5, 'changed' => 1, 'deleted' => 2, 'unchanged' => 0]], 'error' => $ok ? null : 'CDN timeout', 'boost_error' => $boostError];
    }

    public static function states(): array
    {
        return [
            'never ran' => [[], 'none'],
            'queued just now' => [['requestedAt' => self::NOW - 60], 'queued'],
            'queued long ago, scheduler silent' => [['requestedAt' => self::NOW - 16 * 60], 'scheduler_idle'],
            'running' => [['runningSince' => self::NOW - 120, 'requestedAt' => null], 'running'],
            'killed run' => [['runningSince' => self::NOW - 2 * 3600], 'stopped'],
            'ok' => [['lastRun' => self::lastRun(true, 600), 'divisionsTill' => 1, 'settlementsTime' => 1], 'ok'],
            'failed' => [['lastRun' => self::lastRun(false, 600)], 'error'],
            'ok but not updated for days' => [['lastRun' => self::lastRun(true, 4 * 86400), 'divisionsTill' => 1, 'settlementsTime' => 1], 'stale'],
            'killed run recorded' => [['lastRun' => ['started' => 1, 'finished' => self::NOW - 60, 'ok' => false, 'kind' => 'delta', 'counts' => [], 'error' => 'interrupted', 'boost_error' => null]], 'stopped'],
            'queued after a run' => [['lastRun' => self::lastRun(true, 600), 'requestedAt' => self::NOW - 30], 'queued'],
        ];
    }

    #[DataProvider('states')]
    public function testState(array $set, string $state): void
    {
        self::assertSame($state, DictionaryStatusView::vars(self::makeStatus($set), self::NOW, [])['state']);
    }

    public function testErrorTextAndCountsAreShown(): void
    {
        $vars = DictionaryStatusView::vars(self::makeStatus(['lastRun' => self::lastRun(false, 600)]), self::NOW, []);

        self::assertSame('CDN timeout', $vars['error']);
        self::assertSame(['added' => 5, 'changed' => 1, 'deleted' => 2], $vars['changes']);
    }

    public function testBoostWarningOnlyWhenBoostFailed(): void
    {
        self::assertFalse(DictionaryStatusView::vars(self::makeStatus(['lastRun' => self::lastRun(true, 60)]), self::NOW, [])['boost_warning']);
        self::assertTrue(DictionaryStatusView::vars(self::makeStatus(['lastRun' => self::lastRun(true, 60, 'API key is not set')]), self::NOW, [])['boost_warning']);
    }

    public function testFiguresComeFromCounts(): void
    {
        $vars = DictionaryStatusView::vars(self::makeStatus(), self::NOW, ['divisions' => 55137, 'postomats' => 40042, 'settlements' => 27511, 'boosted' => 1384]);

        self::assertSame(55137, $vars['figures']['divisions']);
        self::assertTrue($vars['has_data']);
        // Розряди розділено нерозривним пробілом, як пишуть великі числа українською.
        self::assertSame("55\u{00A0}137", $vars['shown']['divisions']);
        self::assertSame("1\u{00A0}384", $vars['shown']['boosted']);
    }

    public function testButtonIsBusyWhileQueuedOrRunning(): void
    {
        self::assertTrue(DictionaryStatusView::vars(self::makeStatus(['requestedAt' => self::NOW]), self::NOW, [])['busy']);
        self::assertFalse(DictionaryStatusView::vars(self::makeStatus(['requestedAt' => self::NOW - 3600]), self::NOW, [])['busy'], 'a stuck request must not block a new one');
    }
}
