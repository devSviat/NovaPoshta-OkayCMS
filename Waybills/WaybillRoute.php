<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;

/**
 * Який API створює накладну. Новий API створює лише накладну на пункт видачі: він мовчки
 * відкидає післяплату й контроль оплати, ігнорує безготівкову оплату, не має дати відправлення
 * (живі перевірки 05.11 і 07.11.2026) і не приймає ЄДРПОУ; старий API вміє все.
 */
final class WaybillRoute
{
    /** @param string $today d.m.Y */
    public static function for(WaybillDraft $draft, string $today): string
    {
        $plain = $draft->mode === DeliveryMode::Points
            && !$draft->cod
            && $draft->paymentMethod === 'Cash'
            && $draft->shipDate === $today
            && $draft->recipientType === 'PrivatePerson';

        return $plain ? 'new' : 'old';
    }
}
