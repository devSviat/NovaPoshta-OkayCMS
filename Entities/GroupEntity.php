<?php

namespace Okay\Modules\Sviat\NovaPoshta\Entities;

use Okay\Core\Entity\Entity;

/** A group of Nova Poshta categories the customer chooses from (e.g. «Відділення»). */
class GroupEntity extends Entity
{
    protected static $fields = [
        'id',
        'position',
        'enabled',
        'categories',
    ];

    protected static $langFields = [
        'name',
    ];

    protected static $defaultOrderFields = ['position', 'id'];
    protected static $table = 'sviat__novaposhta__groups';
    protected static $langObject = 'group';
    protected static $langTable = 'sviat__novaposhta__groups';
    protected static $tableAlias = 'nps_g';
}
