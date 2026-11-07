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
    protected static $table = 'sviat__novaposhta_waybill';
    protected static $tableAlias = 'nps_w';
}
