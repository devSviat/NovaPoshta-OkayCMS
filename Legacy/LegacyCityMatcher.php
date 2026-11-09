<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/**
 * Наш населений пункт для старого City Ref (getCities). Спершу відомі пари, потім назва — лише якщо вона унікальна,
 * потім Нова Пошта: будь-який пункт видачі цього міста дає свій WarehouseIndex, а це наш номер пункту видачі.
 */
final class LegacyCityMatcher
{
    /** Не збій: тік вичерпав виклики; рядок спробують знову на наступному тіку. */
    public const string BUDGET = 'budget';

    public function __construct(private readonly LegacyPlaces $places)
    {
    }

    /** @return array{0: ?int, 1: ?string} id населеного пункту або причина */
    public function match(string $cityRef, ?string $cityName, LegacyBudget $budget): array
    {
        if ($cityRef !== '' && ($known = $this->places->settlementOfCityRef($cityRef)) !== null) {
            return [$known, null];
        }
        // getCities додає "(… р-н)" до тезок: така назва сама по собі не унікальна, вирішує НП.
        if ($cityName !== null && !str_contains($cityName, '(') && ($place = LegacyNames::place($cityName)) !== ''
            && count($named = $this->places->settlementsNamed($place)) < LegacyPlaces::NAMED_MAX) {
            $found = array_values(array_filter(
                $named,
                static fn(object $s): bool => (int)$s->has_pickup === 1 && LegacyNames::place((string)$s->name_uk) === $place,
            ));
            // Не запам'ятовується як Ref міста: назва — не доказ, а пошук цін довіряє цим парам.
            if (count($found) === 1) {
                return [(int)$found[0]->id, null];
            }
        }
        if ($cityRef === '') {
            return [null, 'legacy_fail_no_place'];
        }
        if (!$budget->takeCall()) {
            return [null, self::BUDGET];
        }
        try {
            $index = $this->places->warehouseIndexOfCity($cityRef);
        } catch (LegacyPlacesUnavailable) {
            return [null, self::BUDGET];
        }
        $division = $index !== null ? $this->places->divisionByNumber($index) : null;
        if ($division === null) {
            return [null, 'legacy_fail_no_place'];
        }
        $this->places->rememberCityRef((int)$division->settlement_id, $cityRef);

        return [(int)$division->settlement_id, null];
    }
}
