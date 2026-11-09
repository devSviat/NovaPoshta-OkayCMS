<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Core\Modules\Extender\ExtenderFacade;

/** Що Нова Пошта стягує при доставці; модулі передоплати зменшують це через ланцюжок. */
final class AmountDue
{
    public function onPickup(object $order, float $totalUah): float
    {
        return ExtenderFacade::execute(__METHOD__, max(0.0, $totalUah), func_get_args());
    }
}
