<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/**
 * Our settlement for an old City Ref (getCities). Known pairs first, then a name only when it is unique,
 * then Nova Poshta: any point of that city gives its WarehouseIndex, which is our point number.
 */
final class LegacyCityMatcher
{
    /** Not a failure: the tick ran out of calls; the row is tried again next tick. */
    public const string BUDGET = 'budget';

    public function __construct(private readonly LegacyPlaces $places)
    {
    }

    /** @return array{0: ?int, 1: ?string} settlement id, or the reason */
    public function match(string $cityRef, ?string $cityName, LegacyBudget $budget): array
    {
        if ($cityRef !== '' && ($known = $this->places->settlementOfCityRef($cityRef)) !== null) {
            return [$known, null];
        }
        if ($cityName !== null && ($place = LegacyNames::place($cityName)) !== '') {
            $found = array_values(array_filter(
                $this->places->settlementsNamed($place),
                static fn(object $s): bool => (int)$s->has_pickup === 1 && LegacyNames::place((string)$s->name_uk) === $place,
            ));
            // Not remembered as a city Ref: a name is not proof, and the price lookups trust those pairs.
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
