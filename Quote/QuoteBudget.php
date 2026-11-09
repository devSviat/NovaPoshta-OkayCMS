<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/**
 * Загальний для магазину ліміт нових розрахунків у Новій Пошті. Обмеження частоти на сесію не зупинить
 * клієнта, який не надсилає cookie (щоразу нова сесія); цей ліміт спільний для всіх.
 */
final class QuoteBudget
{
    public const int LIMIT = 300;
    public const int WINDOW = 600;

    /** @return array{0: bool, 1: string} чи дозволено, і стан, який треба зберегти */
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
