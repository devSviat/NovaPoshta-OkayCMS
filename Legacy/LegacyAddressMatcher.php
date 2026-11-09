<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Населений пункт старого замовлення на адресу — за назвою, областю й районом; тезок ніколи не вгадуємо. */
final class LegacyAddressMatcher
{
    public function __construct(private readonly LegacyPlaces $places)
    {
    }

    /** @return array{0: ?int, 1: ?string} id населеного пункту або мовний ключ причини, чому ні */
    public function match(string $city, ?string $area, ?string $district): array
    {
        $place = LegacyNames::place($city);
        if ($place === '') {
            return [null, 'legacy_fail_no_place'];
        }
        $named = $this->places->settlementsNamed($place);
        if (count($named) >= LegacyPlaces::NAMED_MAX) {
            return [null, 'legacy_fail_namesakes'];
        }
        $found = array_values(array_filter($named, static fn(object $s): bool => LegacyNames::place((string)$s->name_uk) === $place));
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
