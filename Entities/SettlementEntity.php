<?php

namespace Okay\Modules\Sviat\NovaPoshta\Entities;

use Okay\Core\Entity\Entity;

/** Settlements of Ukraine from the Nova Poshta dictionary; `id` is Nova Poshta's id. */
class SettlementEntity extends Entity
{
    protected static $fields = [
        'id',
        'name_uk',
        'name_en',
        'district_uk',
        'district_en',
        'area_uk',
        'area_en',
        'post_code',
        'search_text',
        'boost',
        'prohibited_issuance',
        'has_pickup',
        'row_hash',
        'seen_run',
        'updated_at',
    ];

    protected static $defaultOrderFields = ['boost DESC', 'name_uk'];
    protected static $table = 'sviat__novaposhta_settlements';
    protected static $tableAlias = 'nps_s';

    protected function filter__boosted($value): void
    {
        if ($value) {
            $this->select->where('boost > 0');
        }
    }
}
