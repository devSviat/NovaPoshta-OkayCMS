<?php

namespace Okay\Modules\Sviat\NovaPoshta\Entities;

use Okay\Core\Entity\Entity;

/** Ref міста старого API для населеного пункту, знайдений через номер пункту видачі (Quote\CityRefResolver). */
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
