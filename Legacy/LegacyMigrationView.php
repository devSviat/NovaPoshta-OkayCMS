<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** What the "move from the old modules" block shows (settings page; mockup «НП: простий перехід», 08.11.2026). */
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
        // A one-time move: once done and the old modules are gone, the card goes too.
        if ($enabled === [] && ($status === null || $status === 'done')) {
            return null;
        }
        $report = LegacyReport::fromArray($state['report']);
        $moved = static fn(string $step): int => $report->counts($step)['done'] + $report->counts($step)['existed'];
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
            'preview' => $counts,
            'currentStep' => $steps[$state['step']] ?? null,
            'stepNumber' => min($state['step'] + 1, count($steps)),
            'stepCount' => count($steps),
            'orders' => $moved('orders'),
            'waybills' => $moved('waybills'),
            'failed' => array_sum(array_map(static fn(string $step): int => $report->counts($step)['failed'], $steps)),
            'finishedAt' => ($at = strtotime((string)($state['finished_at'] ?? ''))) ? date('d.m.Y H:i', $at) : null,
            'switch' => $status === 'done' ? array_map(static fn(object $d): array => ['id' => (int)$d->id, 'name' => (string)$d->name], $deliveries) : [],
        ];
    }
}
