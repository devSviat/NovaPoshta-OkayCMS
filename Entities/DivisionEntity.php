<?php

namespace Okay\Modules\Sviat\NovaPoshta\Entities;

use Okay\Core\Entity\Entity;

/** Відділення, пункти видачі PUDO і поштомати України; `id` — id Нової Пошти. */
class DivisionEntity extends Entity
{
    protected static $fields = [
        'id',
        'settlement_id',
        'number',
        'category',
        'status',
        'name_uk',
        'name_en',
        'short_name_uk',
        'short_name_en',
        'address_uk',
        'address_en',
        'post_code',
        'latitude',
        'longitude',
        'max_weight',
        'max_length',
        'max_width',
        'max_height',
        'max_declared_cost',
        'prohibited_issuance',
        'schedule',
        'row_hash',
        'seen_run',
        'updated_at',
    ];

    protected static $defaultOrderFields = ['id'];
    protected static $table = 'sviat__novaposhta__divisions';
    protected static $tableAlias = 'nps_d';
}
