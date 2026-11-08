<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** How the shop's cash on delivery is issued, which decides who pays its commission. */
enum CodKind: string
{
    /** Money transfer: the recipient pays the commission at the branch; the cart shows it apart from the price. */
    case Money = 'money';
    /** Payment control (contract with NP): the sender pays, the customer's price does not change. */
    case Control = 'control';

    /** The kind kept on a payment method (CodPayments::FIELD); 0 and anything unknown — none. */
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
