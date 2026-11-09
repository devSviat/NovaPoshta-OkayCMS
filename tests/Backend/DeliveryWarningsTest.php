<?php

namespace Modules\Sviat\NovaPoshta\Backend;

use Okay\Modules\Sviat\NovaPoshta\Backend\DeliveryWarnings;
use PHPUnit\Framework\TestCase;

/** Сторінка налаштувань називає способи доставки цього модуля, які ядро робить безкоштовними для будь-якого кошика. */
class DeliveryWarningsTest extends TestCase
{
    public function testOnlyOwnPaidMethodsWithZeroThreshold(): void
    {
        $deliveries = [
            (object)['id' => 1, 'module_id' => 75, 'paid' => 1, 'free_from' => '0.00', 'name' => 'НП відділення'],
            (object)['id' => 2, 'module_id' => 75, 'paid' => 1, 'free_from' => '3000.00', 'name' => 'НП адреса'],
            (object)['id' => 3, 'module_id' => 75, 'paid' => 0, 'free_from' => '0.00', 'name' => 'НП безкоштовно'],
            (object)['id' => 4, 'module_id' => 12, 'paid' => 1, 'free_from' => '0.00', 'name' => 'Інший модуль'],
        ];

        self::assertSame([1], array_map(static fn(object $d): int => $d->id, DeliveryWarnings::freeForAll($deliveries, 75)));
        self::assertSame([], DeliveryWarnings::freeForAll($deliveries, 0), 'module id unknown: nothing is ours');
    }
}
