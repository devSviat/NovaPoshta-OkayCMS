<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/**
 * Only what certainly does not fit (design §6): the known weight alone is over the limit, or
 * the cart costs more than the point accepts. A guessed weight never hides a point.
 */
final class DivisionFit
{
    /** The dictionary carries garbage like 2 147 483 kg; such a limit is treated as unknown. */
    private const float MAX_REAL_WEIGHT = 100000;
    private const float MAX_REAL_COST = 1e9;

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

        return $problems;
    }

    private static function limit(mixed $value, float $realisticMax): ?float
    {
        return is_numeric($value) && (float)$value > 0 && (float)$value <= $realisticMax ? (float)$value : null;
    }
}
