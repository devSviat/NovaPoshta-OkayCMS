<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/**
 * Запит, який інший сайт змусив надіслати браузер відвідувача. Маршрут розрахунку ціни зберігає
 * обраний відвідувачем пункт видачі в сесії, тож посилання з іншого сайту не повинне до нього дістатися.
 */
final class CrossSiteRequest
{
    /** @param array<string, mixed> $server $_SERVER */
    public static function is(array $server): bool
    {
        $site = $server['HTTP_SEC_FETCH_SITE'] ?? null;
        if (is_string($site)) {
            return !in_array($site, ['same-origin', 'none'], true);
        }
        // Браузери без fetch metadata: заголовок Origin, якщо його надіслано, усе одно про це скаже.
        $origin = $server['HTTP_ORIGIN'] ?? null;
        if (!is_string($origin)) {
            return false;
        }
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);

        return !is_string($host) || strcasecmp($host . ($port ? ':' . $port : ''), (string)($server['HTTP_HOST'] ?? '')) !== 0;
    }
}
