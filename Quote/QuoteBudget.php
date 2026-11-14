<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/**
 * Загальний для магазину ліміт нових розрахунків у Новій Пошті. Обмеження частоти на сесію не зупинить
 * клієнта, що щоразу починає нову сесію (без cookie або з вигаданою); цей ліміт спільний для всіх.
 */
final class QuoteBudget
{
    public const int LIMIT = 300;
    public const int WINDOW = 600;
    /**
     * Частка одного IP у вікні: третина спільного ліміту, п'ять повних сесій (QuoteThrottle). Стільки
     * вистачає кільком покупцям за одним NAT, а один клієнт не забирає ліміт у всіх інших.
     */
    public const int VISITOR_LIMIT = 100;

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

    /**
     * Частку спільного ліміту рахуємо кожному клієнтові за IP: cookie сесії підробляється, тож ніщо,
     * що надсилає сам клієнт, не виводить його з-під цього ліміту. IP зберігається лише хешем.
     */
    public static function visitor(?string $ip): ?string
    {
        return $ip === null || $ip === '' ? null : substr(sha1('sviat_np|' . self::bucket($ip)), 0, 16);
    }

    /**
     * IPv4 — як є, IPv6 — за /64: одна мережа має їх мільярди, і кожна адреса інакше давала б нову частку.
     * IPv4, записана як IPv6 (`::ffff:203.0.113.7`), — це IPv4. Та сама логіка, що Okay\Core\Security\ClientIp
     * форка: у стоку OkayCMS 4.5.3 цього класу немає, а модуль працює і там.
     */
    private static function bucket(string $ip): string
    {
        $packed = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? inet_pton($ip) : false;
        if ($packed !== false) {
            if (str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
                return (string)inet_ntop(substr($packed, 12));
            }

            return bin2hex(substr($packed, 0, 8)) . '::/64';
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }
}
