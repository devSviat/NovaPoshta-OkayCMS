<?php

namespace Okay\Modules\Sviat\NovaPoshta\Entities;

use Okay\Core\Entity\Entity;

/** The waybill of an order: one per order; `ref` is empty for a waybill attached by number. */
class WaybillEntity extends Entity
{
    protected static $fields = [
        'id',
        'order_id',
        'number',
        'ref',
        'api',
        'services',
        'audit_status',
        'audit',
        'created_at',
        'updated_at',
    ];

    protected static $defaultOrderFields = ['id DESC'];

    /** Waybills still worth asking Nova Poshta about (not final, number known, not being created). */
    protected function filter__due($value): void
    {
        $alias = $this->getTableAlias();
        $closed = implode(',', [...\Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStatus::FINAL, \Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStatus::NOT_FOUND]);
        $this->select->where("({$alias}.status_code IS NULL OR {$alias}.status_code NOT IN ({$closed}))")
            ->where("{$alias}.api <> 'pending'");
    }

    /** `due`: the longest unchecked first. */
    protected function customOrder($order = null, array $orderFields = [], array $additionalData = [])
    {
        if ($order === 'due') {
            $orderFields = ['tracked_at', 'id'];
        }

        return parent::customOrder($order, $orderFields, $additionalData);
    }
    protected static $table = 'sviat__novaposhta_waybill';
    protected static $tableAlias = 'nps_w';
}
