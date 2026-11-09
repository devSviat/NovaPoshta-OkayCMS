<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Storage\Mutex;
use Okay\Modules\Sviat\NovaPoshta\Storage\StateStorage;

/**
 * Одне завдання планувальника виконує всю роботу з довідниками, кнопку в адмінці включно: кнопка лише
 * залишає запит, тож два запуски ніколи не перетинаються, а браузер ніколи не гортає файли.
 */
final class DictionaryRunner
{
    private const int DAILY = 20 * 3600;
    private const int RETRY_AFTER_FAILURE = 3600;
    private const int BOOST_EVERY = 7 * 86400;
    /** Тайм-аут завдання планувальника: запуск, що "в процесі" довше, було вбито. */
    public const int TASK_TIMEOUT = 3600;

    private readonly \Closure $now;

    public function __construct(
        private readonly DictionarySteps $steps,
        private readonly StateStorage $state,
        ?\Closure $now = null,
        private readonly ?Mutex $mutex = null,
    ) {
        $this->now = $now ?? time(...);
    }

    public function requestRun(): void
    {
        $status = DictionaryStatus::load($this->state);
        $status->requestedAt = ($this->now)();
        $status->save($this->state);
    }

    /** Викликається планувальником кожні кілька хвилин; працює, лише коли щось пора виконати. */
    public function tick(): void
    {
        // Перевірка runningSince і його запис — два кроки: два процеси, що стартували разом, пройшли б обидва.
        if ($this->mutex !== null && !$this->mutex->acquire('dictionaries')) {
            return;
        }
        try {
            $this->tickLocked();
        } finally {
            $this->mutex?->release('dictionaries');
        }
    }

    private function tickLocked(): void
    {
        $now = ($this->now)();
        $status = DictionaryStatus::load($this->state);
        if ($status->runningSince !== null) {
            // Сток 4.5.3 ігнорує результат блокування планувальника, тож запуск може ще тривати:
            // два повні запуски одночасно видаляли б рядки один одного.
            if ($now - $status->runningSince < self::TASK_TIMEOUT) {
                return;
            }
            // Убитий (тайм-аут, брак пам'яті): жоден `finally` не виконався, записуємо це як збій, щоб
            // повтор чекав годину, а не стартував кожні 5 хвилин.
            $status->lastRun = [
                'started' => $status->runningSince, 'finished' => $status->runningSince + self::TASK_TIMEOUT,
                'ok' => false, 'kind' => 'delta', 'counts' => [], 'error' => 'interrupted', 'boost_error' => null,
            ];
            $status->runningSince = null;
            $status->save($this->state);
        }
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
            // Натискання кнопки під час запуску замовляє ще один.
            $status->requestedAt = DictionaryStatus::load($this->state)->requestedAt;
            $status->save($this->state);
        }
    }

    private function isDue(DictionaryStatus $status, int $now): bool
    {
        if ($status->requestedAt !== null || $status->lastRun === null) {
            return true;
        }
        $complete = $status->divisionsTill !== null && $status->settlementsTime !== null;
        // Запуск, убитий посередині (тайм-аут планувальника), нічого не записує: відсутні дані скоро запитуються повторно.
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
        // Розрив між збереженим і найстарішою дельтою, яку CDN ще тримає, можна
        // закрити лише базою.
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

    /** Популярність потребує ключа API; без нього решта запуску все одно корисна. */
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
