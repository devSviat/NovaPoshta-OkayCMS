<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** How the shop's cash on delivery is issued, which decides who pays its commission. */
enum CodKind: string
{
    /** Money transfer: the recipient pays the commission at the branch; the cart shows it apart from the price. */
    case Money = 'money';
    /** Payment control (contract with NP): the sender pays, the customer's price does not change. */
    case Control = 'control';

    /**
     * The kind kept on a payment method (CodPayments::FIELD).
     * 1 is the flag of 0.9.0 and earlier, when one kind served the whole shop: it follows that old
     * setting until update_0_10_0 rewrites it, so files deployed before "Update" keep payment control.
     */
    public static function fromFlag(int $flag, ?self $legacy): ?self
    {
        return match ($flag) {
            1 => $legacy ?? self::Money,
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
