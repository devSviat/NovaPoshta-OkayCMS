<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Storage\StateStorage;

/**
 * One scheduler task does all dictionary work, the admin button included: the button only
 * leaves a request, so two runs never overlap and the browser never pages through files.
 */
final class DictionaryRunner
{
    private const int DAILY = 20 * 3600;
    private const int RETRY_AFTER_FAILURE = 3600;
    private const int BOOST_EVERY = 7 * 86400;

    private readonly \Closure $now;

    public function __construct(
        private readonly DictionarySteps $steps,
        private readonly StateStorage $state,
        ?\Closure $now = null,
    ) {
        $this->now = $now ?? time(...);
    }

    public function requestRun(): void
    {
        $status = DictionaryStatus::load($this->state);
        $status->requestedAt = ($this->now)();
        $status->save($this->state);
    }

    /** Called by the scheduler every few minutes; runs only when something is due. */
    public function tick(): void
    {
        $now = ($this->now)();
        $status = DictionaryStatus::load($this->state);
        if (!$this->isDue($status, $now)) {
            return;
        }

        $status->requestedAt = null;
        $status->runningSince = $now;
        $status->save($this->state);

        $run = ['started' => $now, 'finished' => $now, 'ok' => false, 'kind' => 'delta', 'counts' => [], 'error' => null, 'boost_error' => null];
        try {
            $english = $this->steps->siteHasEnglish();
            $languageChanged = $english !== $status->english;

            $this->settlements($status, $english, $languageChanged, $now, $run);
            $this->divisions($status, $english, $languageChanged, $now, $run);
            $status->english = $english;
            $this->boost($status, $now, $run);
            $run['ok'] = true;
        } catch (\Throwable $e) {
            $run['error'] = $e->getMessage();
        } finally {
            $run['finished'] = ($this->now)();
            $status->lastRun = $run;
            $status->runningSince = null;
            $status->save($this->state);
        }
    }

    private function isDue(DictionaryStatus $status, int $now): bool
    {
        if ($status->requestedAt !== null || $status->lastRun === null) {
            return true;
        }
        $complete = $status->divisionsTill !== null && $status->settlementsTime !== null;
        // A run killed mid-way (scheduler timeout) records nothing: missing data is retried soon.
        $wait = $status->lastRun['ok'] && $complete ? self::DAILY : self::RETRY_AFTER_FAILURE;

        return $now - (int)$status->lastRun['finished'] >= $wait;
    }

    private function settlements(DictionaryStatus $status, bool $english, bool $languageChanged, int $run, array &$result): void
    {
        $uk = $this->steps->settlementVersions('uk');
        if ($status->settlementsTime === $uk->baseTime && !$languageChanged) {
            return;
        }
        $en = $english ? $this->steps->settlementVersions('en')->baseUrl : null;
        $result['counts']['settlements'] = $this->steps->fullSettlements($uk->baseUrl, $en, $run)->toArray();
        $status->settlementsTime = $uk->baseTime;
    }

    private function divisions(DictionaryStatus $status, bool $english, bool $languageChanged, int $run, array &$result): void
    {
        $uk = $this->steps->divisionVersions('uk');
        $en = $english ? $this->steps->divisionVersions('en') : null;

        $oldestFrom = $uk->deltas[0]['from'] ?? $uk->baseTime;
        // A gap between what is stored and the oldest delta the CDN still keeps can only be
        // closed by the base.
        if ($status->divisionsTill === null || $status->divisionsTill < $oldestFrom || $languageChanged) {
            $result['kind'] = 'full';
            $result['counts']['divisions'] = $this->steps->fullDivisions($uk->baseUrl, $en?->baseUrl, $run)->toArray();
            $status->divisionsTill = $uk->baseTime;
        }

        $enDeltas = [];
        foreach ($en?->deltas ?? [] as $delta) {
            $enDeltas[$delta['till']] = $delta['url'];
        }
        foreach ($uk->deltas as $delta) {
            if ($delta['till'] <= $status->divisionsTill) {
                continue;
            }
            $counts = $this->steps->deltaDivisions($delta['url'], $enDeltas[$delta['till']] ?? null, $run)->toArray();
            $result['counts']['divisions'] = self::sum($result['counts']['divisions'] ?? [], $counts);
            $status->divisionsTill = $delta['till'];
        }
    }

    /** Popularity needs the API key; without it the rest of the run is still useful. */
    private function boost(DictionaryStatus $status, int $now, array &$result): void
    {
        if ($status->boostAt !== null && $now - $status->boostAt < self::BOOST_EVERY) {
            return;
        }
        try {
            $result['counts']['boost'] = $this->steps->boost();
            $status->boostAt = $now;
        } catch (NpApiException $e) {
            $result['boost_error'] = $e->getMessage();
        }
    }

    private static function sum(array $a, array $b): array
    {
        foreach ($b as $key => $value) {
            $a[$key] = ($a[$key] ?? 0) + $value;
        }

        return $a;
    }
}
