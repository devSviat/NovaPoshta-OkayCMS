<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/**
 * Shop-wide cap on new calculations at Nova Poshta. The per-session throttle cannot stop a client
 * that sends no cookie (a new session every time); this one is shared by everybody.
 */
final class QuoteBudget
{
    public const int LIMIT = 300;
    public const int WINDOW = 600;

    /** @return array{0: bool, 1: string} allowed, and the state to store */
    public static function take(?string $state, int $now, int $limit = self::LIMIT): array
    {
        $data = json_decode((string)$state, true);
        $start = is_array($data) && is_int($data['start'] ?? null) ? $data['start'] : null;
        $count = is_array($data) && is_int($data['count'] ?? null) ? $data['count'] : 0;
        if ($start === null || $now - $start >= self::WINDOW) {
            $start = $now;
            $count = 0;
        }
        $allowed = $count < $limit;
        if ($allowed) {
            $count++;
        }

        return [$allowed, json_encode(['start' => $start, 'count' => $count])];
    }
}
