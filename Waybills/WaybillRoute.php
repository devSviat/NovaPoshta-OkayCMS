<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;

/**
 * Which API makes the waybill (design §4). The new API makes a point waybill only: it silently
 * drops cash on delivery and payment control, ignores non-cash payment and has no ship date
 * (live checks 05.11 and 07.11.2026) and takes no EDRPOU; the old API does everything.
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
