<?php

namespace Okay\Modules\Sviat\NovaPoshta\Entities;

use Okay\Core\Entity\Entity;

/** What the customer chose for an order, with names copied: later dictionary changes must not rewrite history. */
class OrderDeliveryEntity extends Entity
{
    protected static $fields = [
        'id',
        'order_id',
        'delivery_id',
        'mode',
        'settlement_id',
        'settlement_name',
        'district_name',
        'area_name',
        'division_id',
        'division_number',
        'division_category',
        'division_name',
        'division_address',
        'street_name',
        'street_ref',
        'house',
        'flat',
        'entrance',
        'floor',
        'elevator',
        'address_note',
        'lifting',
        'delivery_price',
        'price_source',
        'delivery_term',
        'cod',
        'cod_fee',
        'legacy',
        'created_at',
        'updated_at',
    ];

    protected static $defaultOrderFields = ['id DESC'];
    protected static $table = 'sviat__novaposhta__order_delivery';
    protected static $tableAlias = 'nps_o';
}
