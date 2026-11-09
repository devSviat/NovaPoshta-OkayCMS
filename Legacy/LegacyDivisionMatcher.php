<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Старе відділення в нашому довіднику: той самий населений пункт, номер "…/13" і адреса, коли номери повторюються. */
final class LegacyDivisionMatcher
{
    public function __construct(private readonly LegacyPlaces $places)
    {
    }

    public function match(int $settlementId, ?int $number, string $oldName): ?object
    {
        return $number === null ? null : self::pick($this->places->divisionsOf($settlementId), $number, $oldName);
    }

    /** @param list<object> $divisions пункти видачі одного населеного пункту */
    public static function pick(array $divisions, int $number, string $oldName): ?object
    {
        $suffix = '/' . $number;
        $same = array_values(array_filter($divisions, static fn(object $d): bool => str_ends_with((string)$d->number, $suffix) || (string)$d->number === (string)$number));
        if (count($same) === 1) {
            return $same[0];
        }
        $address = LegacyNames::address($oldName);
        $byAddress = $address === '' ? [] : array_values(array_filter($same, static fn(object $d): bool => LegacyNames::address((string)$d->name_uk) === $address));

        return count($byAddress) === 1 ? $byAddress[0] : null;
    }
}
