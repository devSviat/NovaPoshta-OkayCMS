<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** The settlement of an old address order, by name, region and district; namesakes are never guessed. */
final class LegacyAddressMatcher
{
    public function __construct(private readonly LegacyPlaces $places)
    {
    }

    /** @return array{0: ?int, 1: ?string} settlement id, or the language key of why not */
    public function match(string $city, ?string $area, ?string $district): array
    {
        $place = LegacyNames::place($city);
        if ($place === '') {
            return [null, 'legacy_fail_no_place'];
        }
        $found = array_values(array_filter($this->places->settlementsNamed($place), static fn(object $s): bool => LegacyNames::place((string)$s->name_uk) === $place));
        if ($area !== null && trim($area) !== '') {
            $found = array_values(array_filter($found, static fn(object $s): bool => LegacyNames::area((string)$s->area_uk) === LegacyNames::area($area)));
        }
        if (count($found) > 1 && $district !== null && trim($district) !== '') {
            $found = array_values(array_filter($found, static fn(object $s): bool => LegacyNames::district((string)$s->district_uk) === LegacyNames::district($district)));
        }

        return match (count($found)) {
            0 => [null, 'legacy_fail_no_place'],
            1 => [(int)$found[0]->id, null],
            default => [null, 'legacy_fail_namesakes'],
        };
    }
}
