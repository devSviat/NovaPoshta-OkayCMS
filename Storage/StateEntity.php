<?php

namespace Okay\Modules\Sviat\NovaPoshta\Storage;

use Okay\Core\Entity\Entity;

class StateEntity extends Entity
{
    protected static $fields = [
        'id',
        'name',
        'value',
        'updated_at',
    ];

    protected static $table = 'sviat__novaposhta__state';
    protected static $tableAlias = 'nps';
    protected static $alternativeIdField = 'name';
}
