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
        'status_code',
        'status_text',
        'status_at',
        'scheduled_at',
        'tracking',
        'tracked_at',
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
            ->where("{$alias}.number IS NOT NULL");
    }

    /** @param list<int> $codes status codes */
    protected function filter__status_codes($codes): void
    {
        $codes = array_values(array_filter(array_map('intval', (array)$codes)));
        $this->select->where($this->getTableAlias() . '.status_code IN (' . ($codes === [] ? '0' : implode(',', $codes)) . ')');
    }

    /**
     * Received (9) on or after the date: NP's status date, else when the row was last tracked; never the row's
     * update time, which a migration or a refresh rewrites for old parcels.
     */
    protected function filter__received_since($since): void
    {
        $alias = $this->getTableAlias();
        $this->select->where("{$alias}.status_code = 9")
            ->where("{$alias}.number IS NOT NULL")
            ->where("COALESCE({$alias}.status_at, {$alias}.tracked_at) >= :nps_received_since")
            ->bindValue('nps_received_since', (string)$since);
    }

    /** `due`: the longest unchecked first. */
    protected function customOrder($order = null, array $orderFields = [], array $additionalData = [])
    {
        if ($order === 'due') {
            $orderFields = ['tracked_at', 'id'];
        }

        return parent::customOrder($order, $orderFields, $additionalData);
    }
    protected static $table = 'sviat__novaposhta__waybill';
    protected static $tableAlias = 'nps_w';
}
