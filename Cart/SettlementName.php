<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/** Назва населеного пункту з довідника («селище міського типу Калинівка»), як її читають люди: тип і назва окремо. */
final class SettlementName
{
    /** Тип із довідника (українською, як пише НП) => що показується. */
    private const array TYPES = ['селище міського типу' => 'смт', 'місто' => 'місто', 'село' => 'село', 'селище' => 'селище'];

    /** @return array{0: string, 1: string} тип для показу ('', коли в назві його немає) і назва */
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
