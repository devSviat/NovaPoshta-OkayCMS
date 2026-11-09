<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

use Okay\Modules\Sviat\NovaPoshta\Storage\StateStorage;

/**
 * The "carry over from the old modules" run: the button queues it, the scheduler tick moves it on
 * step by step from the saved cursor, so a big shop is done in portions and a crash resumes.
 */
final class LegacyMigration
{
    public const string STATE = 'legacy_migration';
    private const float TICK_SECONDS = 40.0;
    private const int TICK_CALLS = 60;
    /** A run that has not moved for this long may be started again (an outage or a bad key held it). */
    private const int STALL_SECONDS = 3600;
    /**
     * Carried only by the first finished run: a re-run must not switch cash on delivery back on,
     * or refill settings the manager has changed since.
     */
    private const array ONCE = ['payments', 'settings'];

    /** @var list<LegacyStep> */
    private readonly array $steps;

    public function __construct(private readonly StateStorage $state, private readonly LegacyGate $gate, LegacyStep ...$steps)
    {
        $this->steps = array_values($steps);
    }

    /** @return bool false while not ready, or while a run is queued or going and not stalled */
    public function request(?int $now = null): bool
    {
        $now ??= time();
        $state = $this->state();
        if ($this->gate->problem() !== null || (in_array($state['status'], ['queued', 'running'], true) && !$this->stalled($now))) {
            return false;
        }
        $this->save([
            'status' => 'queued', 'step' => 0, 'cursor' => 0, 'report' => new LegacyReport()->toArray(),
            'requested_at' => date('Y-m-d H:i:s', $now), 'started_at' => null, 'finished_at' => null,
            'progress_at' => $now, 'first_done' => $state['first_done'],
        ]);

        return true;
    }

    /** The scheduler's entry: it builds typed arguments itself, so this one takes none. */
    public function tick(): void
    {
        $this->run(LegacyBudget::forTick(self::TICK_SECONDS, self::TICK_CALLS));
    }

    public function run(LegacyBudget $budget, ?int $now = null): void
    {
        $state = $this->state();
        if (!in_array($state['status'], ['queued', 'running'], true) || $this->gate->problem() !== null) {
            return;
        }
        $report = LegacyReport::fromArray($state['report']);
        $state['status'] = 'running';
        $state['started_at'] ??= date('Y-m-d H:i:s');
        while ($state['step'] < count($this->steps) && $budget->hasTime()) {
            $step = $this->steps[$state['step']];
            $cursor = $state['first_done'] && in_array($step->name(), self::ONCE, true)
                ? LegacyStep::DONE
                : $step->run($state['cursor'], $budget, $report);
            if ($cursor === LegacyStep::DONE) {
                $state['step']++;
                $state['cursor'] = 0;
            } elseif ($cursor === $state['cursor']) {
                // The step waits for calls or time; the next tick goes on from here.
                break;
            } else {
                $state['cursor'] = $cursor;
            }
            $state['progress_at'] = $now ?? time();
            $state['report'] = $report->toArray();
            $this->save($state);
        }
        if ($state['step'] >= count($this->steps)) {
            $state['status'] = 'done';
            $state['finished_at'] = date('Y-m-d H:i:s');
            $state['first_done'] = true;
        }
        $state['report'] = $report->toArray();
        $this->save($state);
    }

    public function stalled(?int $now = null): bool
    {
        $state = $this->state();

        return in_array($state['status'], ['queued', 'running'], true)
            && ($now ?? time()) - $state['progress_at'] > self::STALL_SECONDS;
    }

    public function problem(): ?string
    {
        return $this->gate->problem();
    }

    /** @return array{status: ?string, step: int, cursor: int, report: array, requested_at: ?string, started_at: ?string, finished_at: ?string, progress_at: int, first_done: bool} */
    public function state(): array
    {
        $data = json_decode((string)$this->state->get(self::STATE), true);
        $data = is_array($data) ? $data : [];
        $text = static fn(string $key): ?string => is_string($data[$key] ?? null) ? $data[$key] : null;

        return [
            'status' => in_array($data['status'] ?? null, ['queued', 'running', 'done'], true) ? $data['status'] : null,
            'step' => max(0, (int)($data['step'] ?? 0)),
            'cursor' => max(0, (int)($data['cursor'] ?? 0)),
            'report' => is_array($data['report'] ?? null) ? $data['report'] : [],
            'requested_at' => $text('requested_at'),
            'started_at' => $text('started_at'),
            'finished_at' => $text('finished_at'),
            'progress_at' => max(0, (int)($data['progress_at'] ?? 0)),
            'first_done' => (bool)($data['first_done'] ?? false),
        ];
    }

    /** @return list<string> step names in run order */
    public function stepNames(): array
    {
        return array_map(static fn(LegacyStep $step): string => $step->name(), $this->steps);
    }

    private function save(array $state): void
    {
        $this->state->set(self::STATE, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }
}
