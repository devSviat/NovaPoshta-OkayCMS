<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Cart\DivisionFit;
use Okay\Modules\Sviat\NovaPoshta\Cart\FitProblem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DivisionFitTest extends TestCase
{
    private static function point(mixed $maxWeight, mixed $maxCost = '29000.00'): object
    {
        return (object)['max_weight' => $maxWeight, 'max_declared_cost' => $maxCost];
    }

    public static function cases(): array
    {
        return [
            'exactly the limit fits' => [30.0, false, 100.0, self::point('30.00'), []],
            'a bit over is too heavy' => [30.01, false, 100.0, self::point('30.00'), [FitProblem::TooHeavy]],
            'unknown weight does not exclude' => [0.0, true, 100.0, self::point('2.00'), []],
            'known part over limit excludes even with unknowns' => [12.4, true, 100.0, self::point('10.00'), [FitProblem::TooHeavy]],
            'postomat declared cost' => [1.0, false, 29000.01, self::point('20.00', '29000.00'), [FitProblem::TooExpensive]],
            'both' => [40.0, false, 30000.0, self::point('20.00', '29000.00'), [FitProblem::TooHeavy, FitProblem::TooExpensive]],
            'garbage weight limit is unknown' => [500.0, false, 100.0, self::point('2147483.00'), []],
            'zero limit is unknown' => [500.0, false, 100.0, self::point('0.00'), []],
            'no limits at all' => [500.0, false, 1e7, self::point(null, null), []],
            'garbage cost limit is unknown' => [1.0, false, 5e9, self::point('20.00', '99999999999.00'), []],
        ];
    }

    #[DataProvider('cases')]
    public function testProblems(float $weight, bool $unknown, float $total, object $point, array $problems): void
    {
        self::assertSame($problems, DivisionFit::problems(new CartMetrics($weight, $unknown, $total), $point));
    }
}
