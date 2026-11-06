<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** A visitor's session may start at most this many new calculations at Nova Poshta per window. */
final class QuoteThrottle
{
    private const int LIMIT = 20;
    private const int WINDOW = 600;

    public static function allow(array &$bag, int $now): bool
    {
        $recent = array_values(array_filter(
            is_array($bag['calls'] ?? null) ? $bag['calls'] : [],
            static fn($at): bool => is_int($at) && $now - $at < self::WINDOW,
        ));
        if (count($recent) >= self::LIMIT) {
            $bag['calls'] = $recent;

            return false;
        }
        $recent[] = $now;
        $bag['calls'] = $recent;

        return true;
    }
}
