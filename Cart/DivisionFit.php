<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/**
 * Лише те, що напевно не вміщується: сама відома вага перевищує ліміт, або кошик коштує більше,
 * ніж приймає пункт видачі, або відомий об'єм не вміщується в його найбільше місце.
 * Вгадана вага ніколи не приховує пункт видачі.
 */
final class DivisionFit
{
    /** Довідник містить сміття на кшталт 2 147 483 kg; такий ліміт вважається невідомим. */
    private const float MAX_REAL_WEIGHT = 100000;
    private const float MAX_REAL_COST = 1e9;
    /** Розміри приходять у cm; усе поза (0; 1000] вважається невідомим. */
    private const float MAX_REAL_SIZE = 1000;
    /** Найбільша комірка поштомата, 40×60×30 cm: використовується, коли в поштомата немає розмірів. */
    public const float POSTOMAT_CELL_M3 = 0.072;

    /** @return list<FitProblem> */
    public static function problems(CartMetrics $cart, object $division): array
    {
        $problems = [];
        $maxWeight = self::limit($division->max_weight ?? null, self::MAX_REAL_WEIGHT);
        if ($maxWeight !== null && $cart->knownWeight > $maxWeight + 1e-9) {
            $problems[] = FitProblem::TooHeavy;
        }
        $maxCost = self::limit($division->max_declared_cost ?? null, self::MAX_REAL_COST);
        // Ліміти НП — у гривнях; сума кошика — в основній валюті магазину.
        if ($maxCost !== null && ($cart->totalUah ?? $cart->total) > $maxCost + 1e-9) {
            $problems[] = FitProblem::TooExpensive;
        }
        $maxVolume = self::maxVolume($division);
        if ($cart->knownVolume > 0 && $maxVolume !== null && $cart->knownVolume > $maxVolume + 1e-9) {
            $problems[] = FitProblem::TooBig;
        }

        return $problems;
    }

    /** m³, які пункт видачі приймає в одне місце, або null, коли невідомо. */
    private static function maxVolume(object $division): ?float
    {
        $sizes = array_map(static fn(string $f): ?float => self::limit($division->$f ?? null, self::MAX_REAL_SIZE), ['max_length', 'max_width', 'max_height']);
        if (!in_array(null, $sizes, true)) {
            return $sizes[0] * $sizes[1] * $sizes[2] / 1e6;
        }

        return ($division->category ?? null) === 'Postomat' ? self::POSTOMAT_CELL_M3 : null;
    }

    private static function limit(mixed $value, float $realisticMax): ?float
    {
        return is_numeric($value) && (float)$value > 0 && (float)$value <= $realisticMax ? (float)$value : null;
    }
}
