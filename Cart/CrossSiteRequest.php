<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/**
 * A request another site made the visitor's browser send. The quote route stores the visitor's
 * chosen point in the session, so a link on another site must not reach it.
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
        // Browsers without fetch metadata: the Origin header, when sent, still tells.
        $origin = $server['HTTP_ORIGIN'] ?? null;
        if (!is_string($origin)) {
            return false;
        }
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);

        return !is_string($host) || strcasecmp($host . ($port ? ':' . $port : ''), (string)($server['HTTP_HOST'] ?? '')) !== 0;
    }
}
