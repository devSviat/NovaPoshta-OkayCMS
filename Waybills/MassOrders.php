<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** Що масовому створенню треба знати про замовлення. */
interface MassOrders
{
    /** Замовлення існує й доставляється цим модулем. */
    public function isOurs(int $orderId): bool;

    /** @return WaybillDraft|string чернетка або мовний ключ того, чого бракує замовленню */
    public function draft(int $orderId): WaybillDraft|string;
}
