<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** What the mass action needs to know about an order. */
interface MassOrders
{
    /** The order exists and is delivered by this module. */
    public function isOurs(int $orderId): bool;

    /** @return WaybillDraft|string the draft, or the language key of what the order lacks */
    public function draft(int $orderId): WaybillDraft|string;
}
