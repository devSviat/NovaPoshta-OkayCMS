<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

use Okay\Core\Database;
use Okay\Core\QueryFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;

/** Підказки міст для кошика: з власної таблиці модуля, ніколи не запитом до Нової Пошти. */
final class SettlementSearch
{
    /** Кошик запитує один раз на кожен спосіб доставки на тій самій сторінці. */
    private ?bool $hasAny = null;

    public function __construct(
        private readonly Database $db,
        private readonly QueryFactory $queryFactory,
    ) {
    }

    /** False, доки запуск довідника не завантажив населені пункти, у яких є пункти видачі. */
    public function hasAny(): bool
    {
        if ($this->hasAny !== null) {
            return $this->hasAny;
        }
        $select = $this->queryFactory->newSelect()->cols(['1 AS x'])->from(SettlementEntity::getTable())->where('has_pickup = 1')->limit(1);

        return $this->hasAny = $this->db->query($select) !== false && $this->db->results() !== [];
    }

    /**
     * @param bool $pickupOnly режим пунктів видачі: лише місця з пунктами видачі; адресний режим: кожен населений пункт
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
            // Лише місця, де посилку справді можна забрати (прапорець ставить запуск довідника).
            $select->where('s.has_pickup = 1');
        }
        foreach ($query->words as $i => $word) {
            $select->where("s.search_text LIKE :w{$i}")->bindValue("w{$i}", '%' . $word . '%');
        }
        // search_text починається з власної назви населеного пункту, тож збіг за префіксом там — це збіг назви.
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
                // Показуються окремо: тип звичайним шрифтом, назва жирним.
                'type' => $type,
                'title' => $title,
                'district' => $english && $row->district_en ? $row->district_en : $row->district_uk,
                'area' => $english && $row->area_en ? $row->area_en : $row->area_uk,
            ];
        }

        return $found;
    }
}
