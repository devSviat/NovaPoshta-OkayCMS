<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Що показує блок "перенесення зі старих модулів" на сторінці налаштувань. */
final class LegacyMigrationView
{
    /**
     * @param array{status: ?string, step: int, report: array, finished_at: ?string} $state LegacyMigration::state()
     * @param array<string, int> $counts LegacyData::counts()
     * @param list<string> $enabled старі модулі, що досі увімкнені
     * @param list<object> $deliveries LegacyData::legacyDeliveries()
     * @param list<string> $steps назви кроків у порядку виконання
     * @param ?string $problem LegacyGate::problem()
     * @return array<string, mixed>|null null — нічого показувати
     */
    public static function vars(array $state, array $counts, array $enabled, array $deliveries, array $steps, ?string $problem = null, bool $stalled = false): ?array
    {
        $status = $state['status'];
        // Одноразове перенесення: щойно воно завершене й старих модулів немає, картка теж зникає.
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
