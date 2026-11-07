<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

use Okay\Core\Database;
use Okay\Core\QueryFactory;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Entities\CityRefEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Quote\CityRefStore;

final class LegacyPlacesDb implements LegacyPlaces
{
    private const int TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly Database $db,
        private readonly QueryFactory $queryFactory,
        private readonly CityRefStore $cityRefs,
        private readonly OldApiClient $api,
    ) {
    }

    public function settlement(int $id): ?object
    {
        $rows = $this->select($this->queryFactory->newSelect()
            ->cols(['s.id', 's.name_uk', 's.area_uk', 's.district_uk'])
            ->from(SettlementEntity::getTable() . ' AS s')
            ->where('s.id = :id')
            ->bindValue('id', $id));

        return $rows[0] ?? null;
    }

    public function settlementOfCityRef(string $cityRef): ?int
    {
        $rows = $this->select($this->queryFactory->newSelect()
            ->cols(['c.settlement_id'])
            ->from(CityRefEntity::getTable() . ' AS c')
            ->where('c.city_ref = :ref')
            ->bindValue('ref', $cityRef)
            ->limit(2));

        // A city Ref shared by two settlements is not a pair to trust.
        return count($rows) === 1 ? (int)$rows[0]->settlement_id : null;
    }

    public function rememberCityRef(int $settlementId, string $cityRef): void
    {
        $this->cityRefs->remember($settlementId, $cityRef, time());
    }

    public function settlementsNamed(string $place): array
    {
        return $this->select($this->queryFactory->newSelect()
            ->cols(['s.id', 's.name_uk', 's.area_uk', 's.district_uk', 's.has_pickup'])
            ->from(SettlementEntity::getTable() . ' AS s')
            // Our names carry the type ("село Іванівка"): the place is the whole name or its last words.
            ->where('(s.name_uk = :exact OR s.name_uk LIKE :tail)')
            ->bindValues(['exact' => $place, 'tail' => '% ' . addcslashes($place, '%_\\')])
            ->limit(self::NAMED_MAX));
    }

    public function divisionsOf(int $settlementId): array
    {
        return $this->select($this->divisions()->where('d.settlement_id = :settlement')->bindValue('settlement', $settlementId));
    }

    public function divisionByNumber(string $number): ?object
    {
        $rows = $this->select($this->divisions()->where('d.number = :number')->bindValue('number', $number)->limit(2));

        return count($rows) === 1 ? $rows[0] : null;
    }

    public function warehouseIndexOfCity(string $cityRef): ?string
    {
        try {
            $rows = $this->api->quick(self::TIMEOUT_SECONDS)->call('Address', 'getWarehouses', ['CityRef' => $cityRef, 'Limit' => '1']);
        } catch (NpApiException $e) {
            if (!self::worthRetrying($e->kind)) {
                return null;
            }
            throw new LegacyPlacesUnavailable($e->getMessage(), 0, $e);
        }
        $index = is_array($rows[0] ?? null) ? trim((string)($rows[0]['WarehouseIndex'] ?? '')) : '';

        return $index !== '' ? $index : null;
    }

    /**
     * Whether the row should wait for a later tick. A refused or unreadable answer will not change;
     * a bad key or an outage may be fixed, and guessing "not found" then would leave orders unmatched.
     */
    public static function worthRetrying(NpErrorKind $kind): bool
    {
        return !in_array($kind, [NpErrorKind::Rejected, NpErrorKind::Malformed], true);
    }

    private function divisions(): \Okay\Core\QueryFactory\Select
    {
        return $this->queryFactory->newSelect()
            ->cols(['d.id', 'd.settlement_id', 'd.number', 'd.category', 'd.name_uk', 'd.address_uk'])
            ->from(DivisionEntity::getTable() . ' AS d');
    }

    /** @return list<object> */
    private function select(\Okay\Core\QueryFactory\Select $select): array
    {
        return $this->db->query($select) === false ? [] : array_values($this->db->results());
    }
}
