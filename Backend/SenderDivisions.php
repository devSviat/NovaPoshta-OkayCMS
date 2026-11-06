<?php

namespace Okay\Modules\Sviat\NovaPoshta\Backend;

use Okay\Core\Database;
use Okay\Core\QueryFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;

/** Points a shop can hand parcels in at: working ones except parcel lockers (thousands in big cities). */
final class SenderDivisions
{
    public function __construct(
        private readonly Database $db,
        private readonly QueryFactory $queryFactory,
    ) {
    }

    /** @return list<array{id:int, title:string}> */
    public function forSettlement(int $settlementId, string $lang): array
    {
        if ($settlementId <= 0) {
            return [];
        }
        $select = $this->queryFactory->newSelect()
            ->cols(['d.id', 'd.number', 'd.name_uk', 'd.name_en'])
            ->from(DivisionEntity::getTable() . ' AS d')
            ->where('d.settlement_id = :settlement')
            ->where("d.status = 'Working'")
            ->where("d.category <> 'Postomat'")
            ->bindValue('settlement', $settlementId)
            // `+ 0`: aura reads CAST(… AS …) in ORDER BY as an alias.
            ->orderBy(["SUBSTRING_INDEX(d.number, '/', -1) + 0", 'd.number']);
        if ($this->db->query($select) === false) {
            return [];
        }
        $english = $lang === 'en';

        return array_map(static fn(object $row): array => [
            'id' => (int)$row->id,
            'title' => (string)(($english && $row->name_en ? $row->name_en : $row->name_uk) ?: $row->number),
        ], $this->db->results());
    }
}
