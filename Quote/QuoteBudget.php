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
    /** Частка одного відвідувача у вікні: більше, ніж обмеження на сесію, щоб кілька людей за одним IP не заважали одне одному. */
    public const int VISITOR_LIMIT = 40;

    /**
     * @param ?string $visitor відбиток відвідувача (хеш IP): без нього рахується лише спільний ліміт
     * @return array{0: bool, 1: string} чи дозволено, і стан, який треба зберегти
     */
    public static function take(?string $state, int $now, int $limit = self::LIMIT, ?string $visitor = null, int $visitorLimit = self::VISITOR_LIMIT): array
    {
        $data = json_decode((string)$state, true);
        $start = is_array($data) && is_int($data['start'] ?? null) ? $data['start'] : null;
        $count = is_array($data) && is_int($data['count'] ?? null) ? $data['count'] : 0;
        $visitors = is_array($data) && is_array($data['visitors'] ?? null) ? $data['visitors'] : [];
        if ($start === null || $now - $start >= self::WINDOW) {
            $start = $now;
            $count = 0;
            $visitors = [];
        }
        $own = $visitor !== null && is_int($visitors[$visitor] ?? null) ? $visitors[$visitor] : 0;
        $allowed = $count < $limit && ($visitor === null || $own < $visitorLimit);
        if ($allowed) {
            $count++;
            if ($visitor !== null) {
                $visitors[$visitor] = $own + 1;
            }
        }
        $next = ['start' => $start, 'count' => $count];

        return [$allowed, json_encode($visitors === [] ? $next : $next + ['visitors' => $visitors])];
    }
}
