<?php

namespace Okay\Modules\Sviat\NovaPoshta\Entities;

use Okay\Core\Entity\Entity;

/** Old API city Ref of a settlement, found through a point number (Quote\CityRefResolver). */
class CityRefEntity extends Entity
{
    protected static $fields = [
        'settlement_id',
        'city_ref',
        'checked_at',
    ];

    protected static $defaultOrderFields = ['settlement_id'];
    protected static $table = 'sviat__novaposhta__city_refs';
    protected static $tableAlias = 'nps_cr';
}
