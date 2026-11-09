<?php

namespace Okay\Modules\Sviat\NovaPoshta\Backend;

use Okay\Modules\Sviat\NovaPoshta\Quote\FreeDelivery;

final class DeliveryWarnings
{
    /**
     * @param iterable<object> $deliveries
     * @return list<object> способи доставки цього модуля, які ядро робить безкоштовними для будь-якого кошика (платні, поріг 0)
     */
    public static function freeForAll(iterable $deliveries, int $moduleId): array
    {
        $found = [];
        foreach ($deliveries as $delivery) {
            if ($moduleId > 0 && (int)($delivery->module_id ?? 0) === $moduleId && FreeDelivery::freeForAll($delivery)) {
                $found[] = $delivery;
            }
        }

        return $found;
    }
}
