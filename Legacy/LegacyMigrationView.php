<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** What the "move from the old modules" block shows (settings page, mockup Migration.dc.html). */
final class LegacyMigrationView
{
    /**
     * @param array{status: ?string, step: int, report: array, finished_at: ?string} $state LegacyMigration::state()
     * @param array<string, int> $counts LegacyData::counts()
     * @param list<string> $enabled old modules still on
     * @param list<object> $deliveries LegacyData::legacyDeliveries()
     * @param list<string> $steps step names in run order
     * @param ?string $problem LegacyGate::problem()
     * @return array<string, mixed>|null null — nothing to show
     */
    public static function vars(array $state, array $counts, array $enabled, array $deliveries, array $steps, ?string $problem = null, bool $stalled = false): ?array
    {
        $status = $state['status'];
        if ($status === null && $enabled === []) {
            return null;
        }
        $report = LegacyReport::fromArray($state['report']);
        $rows = [];
        foreach ($steps as $step) {
            $rows[$step] = $report->counts($step);
        }

        $running = in_array($status, ['queued', 'running'], true);

        return [
            'problem' => $problem,
            'stalled' => $running && $stalled,
            'canStart' => $problem === null && (!$running || $stalled),
            'phase' => match ($status) {
                'queued', 'running' => 'running',
                'done' => 'done',
                default => 'before',
            },
            'enabled' => $enabled,
            'preview' => $counts,
            'currentStep' => $steps[$state['step']] ?? null,
            'stepNumber' => min($state['step'] + 1, count($steps)),
            'stepCount' => count($steps),
            'rows' => $rows,
            'fails' => $report->fails(),
            'moreFails' => $report->moreFails(),
            'finishedAt' => $state['finished_at'] ?? null,
            'switch' => $status === 'done' ? array_map(static fn(object $d): array => [
                'id' => (int)$d->id,
                'name' => (string)$d->name,
                'mode' => LegacyTables::modeOf($d->service_type)?->value,
            ], $deliveries) : [],
        ];
    }
}
