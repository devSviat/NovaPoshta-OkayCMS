<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** How the shop's cash on delivery is issued, which decides who pays its commission. */
enum CodKind: string
{
    /** Money transfer: the recipient pays the commission, so it is part of the delivery price. */
    case Money = 'money';
    /** Payment control (contract with NP): the sender pays, the customer's price does not change. */
    case Control = 'control';
}
