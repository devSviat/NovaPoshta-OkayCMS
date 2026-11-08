<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

use Okay\Core\Database;
use Okay\Core\QueryFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\CityRefEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;

final class DbCityRefStore implements CityRefStore
{
    public function __construct(
        private readonly Database $db,
        private readonly QueryFactory $queryFactory,
    ) {
    }

    public function cached(int $settlementId): ?array
    {
        $select = $this->queryFactory->newSelect()->cols(['city_ref', 'checked_at'])
            ->from(CityRefEntity::getTable())
            ->where('settlement_id = :id')->bindValue('id', $settlementId);
        if ($this->db->query($select) === false) {
            return null;
        }
        $row = $this->db->result();

        return $row ? ['ref' => $row->city_ref !== null ? (string)$row->city_ref : null, 'checkedAt' => (int)strtotime((string)$row->checked_at)] : null;
    }

    public function remember(int $settlementId, ?string $ref, int $now): void
    {
        $insert = $this->queryFactory->newInsert()->into(CityRefEntity::getTable())
            ->cols(['settlement_id' => $settlementId, 'city_ref' => $ref, 'checked_at' => date('Y-m-d H:i:s', $now)])
            ->onDuplicateKeyUpdate('city_ref', 'VALUES(city_ref)')
            ->onDuplicateKeyUpdate('checked_at', 'VALUES(checked_at)');
        $this->db->query($insert);
    }

    public function bridgeNumbers(int $settlementId, int $limit): array
    {
        // A number repeated inside one settlement (3 such pairs in Ukraine) may lead the old API
        // to another record; such numbers are not used as a bridge.
        $select = $this->queryFactory->newSelect()->cols(['d.number'])
            ->from(DivisionEntity::getTable() . ' AS d')
            ->where('d.settlement_id = :id')
            ->where("d.status = 'Working'")
            ->groupBy(['d.number'])
            ->having('COUNT(*) = 1')
            ->orderBy(["SUBSTRING_INDEX(d.number, '/', -1) + 0"])
            ->limit($limit)
            ->bindValue('id', $settlementId);
        if ($this->db->query($select) === false) {
            return [];
        }

        return array_map(static fn(object $row): string => (string)$row->number, $this->db->results());
    }
}
