<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** Дата, на яку оформлюється накладна: останній за день забір у магазину вирішує — сьогодні чи завтра. */
final class ShipDate
{
    /** @param string $cutoff HH:MM; порожнє означає, що кожна накладна — на завтра */
    public static function for(\DateTimeImmutable $now, string $cutoff): string
    {
        $today = preg_match('/^(\d{1,2}):(\d{2})$/', $cutoff, $m)
            && $now < $now->setTime((int)$m[1], (int)$m[2]);

        return ($today ? $now : $now->modify('+1 day'))->format('d.m.Y');
    }
}
