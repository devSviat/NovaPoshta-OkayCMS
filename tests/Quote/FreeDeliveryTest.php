<?php

namespace Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Quote\FreeDelivery;
use PHPUnit\Framework\TestCase;

/**
 * Правило ядра (DeliveriesHelper): безкоштовно, якщо free_from не більший за суму товарів. Тому 0
 * означає безкоштовно для будь-якого кошика (рішення автора 08.11.2026: як у ядрі, з попередженням).
 */
class FreeDeliveryTest extends TestCase
{
    public function testZeroThresholdIsAlwaysFree(): void
    {
        self::assertTrue(FreeDelivery::applies((object)['free_from' => 0], 10.0));
        self::assertTrue(FreeDelivery::applies((object)[], 10.0));
    }

    public function testThreshold(): void
    {
        self::assertFalse(FreeDelivery::applies((object)['free_from' => 2000], 1999.99));
        self::assertTrue(FreeDelivery::applies((object)['free_from' => 2000], 2000.0), 'the core is free at the threshold itself');
        self::assertTrue(FreeDelivery::applies((object)['free_from' => '2000.00'], 2500.0));
    }

    public function testZeroThresholdOnAPaidMethodIsTheTrap(): void
    {
        self::assertTrue(FreeDelivery::freeForAll((object)['paid' => 1, 'free_from' => 0]));
        self::assertTrue(FreeDelivery::freeForAll((object)['paid' => 1, 'free_from' => '0.00']));
        self::assertFalse(FreeDelivery::freeForAll((object)['paid' => 1, 'free_from' => 5000]));
        self::assertFalse(FreeDelivery::freeForAll((object)['paid' => 0, 'free_from' => 0]), 'marked free on purpose');
    }
}
