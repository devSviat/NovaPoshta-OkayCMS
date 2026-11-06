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

    private static function sized(string $category, mixed $l, mixed $w, mixed $h): object
    {
        return (object)['category' => $category, 'max_weight' => '30.00', 'max_declared_cost' => '29000.00', 'max_length' => $l, 'max_width' => $w, 'max_height' => $h];
    }

    public static function volumeCases(): array
    {
        return [
            'locker cell 60×40×30 takes 0.07' => [0.07, self::sized('Postomat', 60, 40, 30), []],
            'locker cell is too small for 0.08' => [0.08, self::sized('Postomat', 60, 40, 30), [FitProblem::TooBig]],
            'locker without sizes uses the largest cell' => [0.08, self::sized('Postomat', 0, 0, 0), [FitProblem::TooBig]],
            'branch 120×70×70 takes 0.58' => [0.58, self::sized('PostBranch', 120, 70, 70), []],
            'branch 120×70×70 is too small for 0.6' => [0.6, self::sized('PostBranch', 120, 70, 70), [FitProblem::TooBig]],
            'branch with absurd sizes has no limit' => [5.0, self::sized('PostBranch', 0, 70, 99999), []],
            'unknown volume never excludes' => [0.0, self::sized('Postomat', 60, 40, 30), []],
        ];
    }

    #[DataProvider('volumeCases')]
    public function testVolume(float $volume, object $point, array $problems): void
    {
        self::assertSame($problems, DivisionFit::problems(new CartMetrics(1.0, false, 100.0, $volume), $point));
    }

    public function testDeclaredCostLimitIsComparedInHryvnias(): void
    {
        // Main currency USD: a 1000 USD cart is 41 000 UAH, over a locker's 29 000 UAH.
        $cart = new CartMetrics(1.0, false, 1000.0)->inUah(41000.0);

        self::assertSame([FitProblem::TooExpensive], DivisionFit::problems($cart, self::point('20.00', '29000.00')));
        self::assertSame([], DivisionFit::problems(new CartMetrics(1.0, false, 1000.0), self::point('20.00', '29000.00')), 'without a rate the total is taken as is');
    }
}
