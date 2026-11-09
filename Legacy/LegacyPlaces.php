<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Наш довідник у тому вигляді, що потрібен перенесенню, і одне питання, яке воно може поставити Новій Пошті. */
interface LegacyPlaces
{
    /** Пошук за назвою, що повертає стільки рядків, міг бути обрізаний: його результат не доводить унікальності. */
    public const int NAMED_MAX = 1000;

    /** @return object|null id, name_uk, area_uk, district_uk */
    public function settlement(int $id): ?object;

    public function settlementOfCityRef(string $cityRef): ?int;

    public function rememberCityRef(int $settlementId, string $cityRef): void;

    /** @return list<object> id, name_uk, area_uk, district_uk, has_pickup — населені пункти, чия назва містить $place */
    public function settlementsNamed(string $place): array;

    /** @return list<object> id, settlement_id, number, category, name_uk, address_uk */
    public function divisionsOf(int $settlementId): array;

    public function divisionByNumber(string $number): ?object;

    /** WarehouseIndex будь-якого пункту видачі міста старого API (Address.getWarehouses); null, коли НП не знає жодного. */
    public function warehouseIndexOfCity(string $cityRef): ?string;
}
