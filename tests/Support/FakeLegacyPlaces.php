<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyPlaces;

/** Наш довідник у масивах; `indexOfCity` заміняє Address.getWarehouses і рахує його виклики. */
final class FakeLegacyPlaces implements LegacyPlaces
{
    /** @var list<object> id, name_uk, area_uk, district_uk, has_pickup */
    public array $settlements = [];
    /** @var list<object> id, settlement_id, number, name_uk, category */
    public array $divisions = [];
    /** @var array<string, int> */
    public array $cityRefs = [];
    /** @var array<string, string> ref міста => WarehouseIndex у цьому місті */
    public array $indexOfCity = [];
    public int $apiCalls = 0;

    public function settlement(int $id): ?object
    {
        foreach ($this->settlements as $s) {
            if ((int)$s->id === $id) {
                return $s;
            }
        }

        return null;
    }

    public function settlementOfCityRef(string $cityRef): ?int
    {
        return $this->cityRefs[$cityRef] ?? null;
    }

    public function rememberCityRef(int $settlementId, string $cityRef): void
    {
        $this->cityRefs[$cityRef] = $settlementId;
    }

    public function settlementsNamed(string $place): array
    {
        return array_values(array_filter($this->settlements, static fn(object $s): bool => str_contains(mb_strtolower($s->name_uk), $place)));
    }

    public function divisionsOf(int $settlementId): array
    {
        return array_values(array_filter($this->divisions, static fn(object $d): bool => (int)$d->settlement_id === $settlementId));
    }

    public function divisionByNumber(string $number): ?object
    {
        foreach ($this->divisions as $division) {
            if ($division->number === $number) {
                return $division;
            }
        }

        return null;
    }

    public function warehouseIndexOfCity(string $cityRef): ?string
    {
        $this->apiCalls++;
        if (($this->indexOfCity[$cityRef] ?? null) === 'down') {
            throw new \Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyPlacesUnavailable('timeout');
        }

        return $this->indexOfCity[$cityRef] ?? null;
    }
}
