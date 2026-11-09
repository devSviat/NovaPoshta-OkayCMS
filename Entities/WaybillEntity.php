<?php

namespace Okay\Modules\Sviat\NovaPoshta\Entities;

use Okay\Core\Entity\Entity;

/** Накладна замовлення: одна на замовлення; `ref` порожній для накладної, прив'язаної за номером. */
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

    /** Накладні, про які ще варто питати Нову Пошту (не фінальні, номер відомий, не в процесі створення). */
    protected function filter__due($value): void
    {
        $alias = $this->getTableAlias();
        $closed = implode(',', [...\Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStatus::FINAL, \Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStatus::NOT_FOUND]);
        $this->select->where("({$alias}.status_code IS NULL OR {$alias}.status_code NOT IN ({$closed}))")
            ->where("{$alias}.number IS NOT NULL");
    }

    protected function filter__created_since($since): void
    {
        $this->select->where("{$this->getTableAlias()}.created_at >= :nps_created_since")
            ->bindValue('nps_created_since', (string)$since);
    }

    /**
     * Отримані (9) у дату або після неї: дата статусу НП, інакше час останнього трекінгу рядка; ніколи не час
     * оновлення рядка, який міграція чи оновлення переписує для старих посилок.
     */
    protected function filter__received_since($since): void
    {
        $alias = $this->getTableAlias();
        $this->select->where("{$alias}.status_code = 9")
            ->where("{$alias}.number IS NOT NULL")
            ->where("COALESCE({$alias}.status_at, {$alias}.tracked_at) >= :nps_received_since")
            ->bindValue('nps_received_since', (string)$since);
    }

    /** `due`: спершу ті, що найдовше не перевірялися. */
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
