<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/** A settlement's dictionary name («селище міського типу Калинівка») as people read it: type and title apart. */
final class SettlementName
{
    /** Dictionary type (Ukrainian, as NP writes it) => what is shown. */
    private const array TYPES = ['селище міського типу' => 'смт', 'місто' => 'місто', 'село' => 'село', 'селище' => 'селище'];

    /** @return array{0: string, 1: string} the type to show ('' when the name has none) and the title */
    public static function split(string $name): array
    {
        $name = trim($name);
        foreach (self::TYPES as $type => $shown) {
            if (mb_stripos($name, $type . ' ') === 0) {
                return [$shown, trim(mb_substr($name, mb_strlen($type) + 1))];
            }
        }

        return ['', $name];
    }
}
