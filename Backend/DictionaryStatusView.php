<?php

namespace Okay\Modules\Sviat\NovaPoshta\Backend;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryStatus;

/** Template data for the dictionaries block. */
final class DictionaryStatusView
{
    /** The scheduler task runs every 5 minutes; a request older than this means it does not run. */
    private const int SCHEDULER_SILENT_AFTER = 15 * 60;
    /** The scheduler task timeout: a run "in progress" for longer was killed. */
    private const int RUN_DEAD_AFTER = 3600;

    /** @param array{divisions?:int, postomats?:int, settlements?:int, boosted?:int} $counts */
    public static function vars(DictionaryStatus $status, int $now, array $counts, ?\DateTimeZone $timezone = null): array
    {
        $state = self::state($status, $now);
        $run = $status->lastRun;
        $changes = $run['counts']['divisions'] ?? [];

        return [
            'state' => $state,
            'busy' => $state === 'queued' || $state === 'running',
            'has_data' => ($counts['divisions'] ?? 0) > 0,
            'figures' => [
                'divisions' => (int)($counts['divisions'] ?? 0),
                'postomats' => (int)($counts['postomats'] ?? 0),
                'settlements' => (int)($counts['settlements'] ?? 0),
                'boosted' => (int)($counts['boosted'] ?? 0),
            ],
            'updated_at' => isset($run['finished']) && $run['ok']
                ? (new \DateTimeImmutable('@' . (int)$run['finished']))
                    ->setTimezone($timezone ?? new \DateTimeZone(date_default_timezone_get()))
                    ->format('d.m.Y H:i')
                : null,
            'changes' => [
                'added' => (int)($changes['added'] ?? 0),
                'changed' => (int)($changes['changed'] ?? 0),
                'deleted' => (int)($changes['deleted'] ?? 0),
            ],
            'error' => $state === 'error' ? (string)($run['error'] ?? '') : null,
            'boost_warning' => ($run['boost_error'] ?? null) !== null,
        ];
    }

    private static function state(DictionaryStatus $status, int $now): string
    {
        if ($status->runningSince !== null) {
            return $now - $status->runningSince < self::RUN_DEAD_AFTER ? 'running' : 'stopped';
        }
        if ($status->requestedAt !== null) {
            return $now - $status->requestedAt < self::SCHEDULER_SILENT_AFTER ? 'queued' : 'scheduler_idle';
        }

        return match (true) {
            $status->lastRun === null => 'none',
            !$status->lastRun['ok'] => 'error',
            default => 'ok',
        };
    }
}
