<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

use Okay\Core\Database;
use Okay\Core\QueryFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;

/** City suggestions for the cart: from the module's own table, never a request to Nova Poshta. */
final class SettlementSearch
{
    /** The cart asks once per delivery method on the same page. */
    private ?bool $hasAny = null;

    public function __construct(
        private readonly Database $db,
        private readonly QueryFactory $queryFactory,
    ) {
    }

    /** False until the dictionary run has loaded settlements that have pickup points. */
    public function hasAny(): bool
    {
        if ($this->hasAny !== null) {
            return $this->hasAny;
        }
        $select = $this->queryFactory->newSelect()->cols(['1 AS x'])->from(SettlementEntity::getTable())->where('has_pickup = 1')->limit(1);

        return $this->hasAny = $this->db->query($select) !== false && $this->db->results() !== [];
    }

    /**
     * @param bool $pickupOnly points mode: only places with pickup points; address mode: every settlement
     * @return list<array{id:int, name:string, type:string, title:string, district:?string, area:?string}>
     */
    public function find(string $raw, string $lang, int $limit = 10, bool $pickupOnly = true): array
    {
        $query = SettlementQuery::parse($raw);
        if ($query === null) {
            return [];
        }
        $english = $lang === 'en';

        $select = $this->queryFactory->newSelect()
            ->cols(['s.id', 's.name_uk', 's.name_en', 's.district_uk', 's.district_en', 's.area_uk', 's.area_en'])
            ->from(SettlementEntity::getTable() . ' AS s')
            ->limit($limit);
        if ($pickupOnly) {
            // Only places a parcel can actually be picked up in (flag kept by the dictionary run).
            $select->where('s.has_pickup = 1');
        }
        foreach ($query->words as $i => $word) {
            $select->where("s.search_text LIKE :w{$i}")->bindValue("w{$i}", '%' . $word . '%');
        }
        // search_text starts with the settlement's own name, so a prefix match there is a name match.
        $select->orderBy(['(s.search_text LIKE :prefix) DESC', 's.boost DESC', 'CHAR_LENGTH(s.name_uk)', 's.id'])
            ->bindValue('prefix', $query->prefix . '%');

        if ($this->db->query($select) === false) {
            return [];
        }

        $found = [];
        foreach ($this->db->results() as $row) {
            $name = $english && $row->name_en ? $row->name_en : $row->name_uk;
            [$type, $title] = SettlementName::split((string)$name);
            $found[] = [
                'id' => (int)$row->id,
                'name' => $name,
                // Shown apart: the type plain, the title bold.
                'type' => $type,
                'title' => $title,
                'district' => $english && $row->district_en ? $row->district_en : $row->district_uk,
                'area' => $english && $row->area_en ? $row->area_en : $row->area_uk,
            ];
        }

        return $found;
    }
}
