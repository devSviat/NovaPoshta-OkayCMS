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

    /** @var list<LegacyStep> */
    private readonly array $steps;

    public function __construct(private readonly StateStorage $state, LegacyStep ...$steps)
    {
        $this->steps = array_values($steps);
    }

    /** @return bool false while a run is queued or going */
    public function request(): bool
    {
        if (in_array($this->state()['status'], ['queued', 'running'], true)) {
            return false;
        }
        $this->save([
            'status' => 'queued', 'step' => 0, 'cursor' => 0, 'report' => new LegacyReport()->toArray(),
            'requested_at' => date('Y-m-d H:i:s'), 'started_at' => null, 'finished_at' => null,
        ]);

        return true;
    }

    public function tick(?LegacyBudget $budget = null): void
    {
        $state = $this->state();
        if (!in_array($state['status'], ['queued', 'running'], true)) {
            return;
        }
        $budget ??= LegacyBudget::forTick(self::TICK_SECONDS, self::TICK_CALLS);
        $report = LegacyReport::fromArray($state['report']);
        $state['status'] = 'running';
        $state['started_at'] ??= date('Y-m-d H:i:s');
        while ($state['step'] < count($this->steps) && $budget->hasTime()) {
            $cursor = $this->steps[$state['step']]->run($state['cursor'], $budget, $report);
            if ($cursor === LegacyStep::DONE) {
                $state['step']++;
                $state['cursor'] = 0;
            } elseif ($cursor === $state['cursor']) {
                // The step waits for calls or time; the next tick goes on from here.
                break;
            } else {
                $state['cursor'] = $cursor;
            }
            $state['report'] = $report->toArray();
            $this->save($state);
        }
        if ($state['step'] >= count($this->steps)) {
            $state['status'] = 'done';
            $state['finished_at'] = date('Y-m-d H:i:s');
        }
        $state['report'] = $report->toArray();
        $this->save($state);
    }

    /** @return array{status: ?string, step: int, cursor: int, report: array, requested_at: ?string, started_at: ?string, finished_at: ?string} */
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
