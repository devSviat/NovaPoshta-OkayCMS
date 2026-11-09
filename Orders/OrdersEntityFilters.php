<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Core\Modules\AbstractModuleEntityFilter;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity;

class OrdersEntityFilters extends AbstractModuleEntityFilter
{
    /** @param list<int> $codes замовлення, чия накладна має один із цих статусів НП */
    public function filter__np_status_codes($codes, $filter)
    {
        $codes = array_values(array_filter(array_map(intval(...), (array)$codes)));
        $orders = OrdersEntity::getTableAlias();
        $waybills = WaybillEntity::getTable();
        $this->select->where("{$orders}.id IN (SELECT order_id FROM {$waybills} WHERE status_code IN (" . ($codes === [] ? '0' : implode(',', $codes)) . '))');
    }

    /** Замовлення з доставкою цим модулем (пункт видачі чи адресу збережено), що ще не мають накладної. */
    public function filter__np_without_waybill($value, $filter)
    {
        if (empty($value)) {
            return;
        }
        $orders = OrdersEntity::getTableAlias();
        $snapshots = OrderDeliveryEntity::getTable();
        $waybills = WaybillEntity::getTable();
        // Прості підзапити IN: Aura бере пари alias.column у лапки всередині EXISTS (…), і SQL ламається.
        $this->select->where("{$orders}.id IN (SELECT order_id FROM {$snapshots})")
            ->where("{$orders}.id NOT IN (SELECT order_id FROM {$waybills} WHERE number IS NOT NULL)");
    }
}
