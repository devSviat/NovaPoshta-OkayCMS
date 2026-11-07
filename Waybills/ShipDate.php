<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** The date a waybill is issued for: the shop's last pickup of the day decides today or tomorrow. */
final class ShipDate
{
    /** @param string $cutoff HH:MM; empty means every waybill is for tomorrow */
    public static function for(\DateTimeImmutable $now, string $cutoff): string
    {
        $today = preg_match('/^(\d{1,2}):(\d{2})$/', $cutoff, $m)
            && $now < $now->setTime((int)$m[1], (int)$m[2]);

        return ($today ? $now : $now->modify('+1 day'))->format('d.m.Y');
    }
}
