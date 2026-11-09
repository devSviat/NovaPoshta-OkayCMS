<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** Як оформлено післяплату магазину — від цього залежить, хто платить її комісію. */
enum CodKind: string
{
    /** Грошовий переказ: комісію платить отримувач у відділенні; кошик показує її окремо від ціни. */
    case Money = 'money';
    /** Контроль оплати (договір з НП): платить відправник, ціна для покупця не змінюється. */
    case Control = 'control';

    /** Вид, збережений у способі оплати (CodPayments::FIELD); 0 і все невідоме — жоден. */
    public static function fromFlag(int $flag): ?self
    {
        return match ($flag) {
            2 => self::Money,
            3 => self::Control,
            default => null,
        };
    }

    public function flag(): int
    {
        return match ($this) {
            self::Money => 2,
            self::Control => 3,
        };
    }
}
