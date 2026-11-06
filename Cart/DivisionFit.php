<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/**
 * Only what certainly does not fit (design §6): the known weight alone is over the limit, or
 * the cart costs more than the point accepts, or the known volume does not fit its largest place. A guessed weight never hides a point.
 */
final class DivisionFit
{
    /** The dictionary carries garbage like 2 147 483 kg; such a limit is treated as unknown. */
    private const float MAX_REAL_WEIGHT = 100000;
    private const float MAX_REAL_COST = 1e9;
    /** Sizes come in cm; anything outside (0; 1000] is treated as unknown. */
    private const float MAX_REAL_SIZE = 1000;
    /** The largest locker cell, 40×60×30 cm (§6): used when a locker has no sizes. */
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
        if ($maxCost !== null && $cart->total > $maxCost + 1e-9) {
            $problems[] = FitProblem::TooExpensive;
        }
        $maxVolume = self::maxVolume($division);
        if ($cart->knownVolume > 0 && $maxVolume !== null && $cart->knownVolume > $maxVolume + 1e-9) {
            $problems[] = FitProblem::TooBig;
        }

        return $problems;
    }

    /** m³ the point takes in one place, or null when unknown. */
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
