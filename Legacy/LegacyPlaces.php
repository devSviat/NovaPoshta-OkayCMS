<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Our dictionary as the migration needs it, and the one Nova Poshta question it may ask. */
interface LegacyPlaces
{
    /** A name search that returns this many rows may have been cut: its result is not proof of uniqueness. */
    public const int NAMED_MAX = 1000;

    /** @return object|null id, name_uk, area_uk, district_uk */
    public function settlement(int $id): ?object;

    public function settlementOfCityRef(string $cityRef): ?int;

    public function rememberCityRef(int $settlementId, string $cityRef): void;

    /** @return list<object> id, name_uk, area_uk, district_uk, has_pickup — settlements whose name contains $place */
    public function settlementsNamed(string $place): array;

    /** @return list<object> id, settlement_id, number, category, name_uk, address_uk */
    public function divisionsOf(int $settlementId): array;

    public function divisionByNumber(string $number): ?object;

    /** A WarehouseIndex of any point of the old API's city (Address.getWarehouses); null when NP knows none. */
    public function warehouseIndexOfCity(string $cityRef): ?string;
}
