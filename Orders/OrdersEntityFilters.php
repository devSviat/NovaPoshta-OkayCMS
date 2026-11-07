<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Core\Modules\AbstractModuleEntityFilter;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity;

/** Filters this module adds to the core orders entity. */
class OrdersEntityFilters extends AbstractModuleEntityFilter
{
    /** Orders delivered by this module (a point or address is saved) that have no waybill yet. */
    public function filter__np_without_waybill($value, $filter)
    {
        if (empty($value)) {
            return;
        }
        $orders = OrdersEntity::getTableAlias();
        $snapshots = OrderDeliveryEntity::getTable();
        $waybills = WaybillEntity::getTable();
        // Plain IN subqueries: Aura quotes alias.column pairs inside EXISTS (…) into broken SQL.
        $this->select->where("{$orders}.id IN (SELECT order_id FROM {$snapshots})")
            ->where("{$orders}.id NOT IN (SELECT order_id FROM {$waybills} WHERE api <> 'pending')");
    }
}
