<?php

namespace Okay\Modules\Sviat\NovaPoshta\Payments;

use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;

/** The "Nova Poshta cash on delivery" block of the core payment method page (payment_cod.tpl). */
final class CodPaymentInput
{
    /** Hidden input of the block: without it the POST came from elsewhere and the column is left as it is. */
    public const string MARKER = 'sviat_np_cod_form';
    public const string SWITCH = 'sviat_np_cod';
    public const string KIND = 'sviat_np_cod_kind';

    /**
     * @param array<string, mixed> $post
     * @param mixed $module the payment's posted module; an online payment is never cash on delivery
     * @return int|null CodKind flag to store, or null to leave the column untouched
     */
    public static function flag(array $post, mixed $module): ?int
    {
        if (($post[self::MARKER] ?? null) !== '1') {
            return null;
        }
        if (!CodPayments::isManual($module) || ($post[self::SWITCH] ?? null) !== '1') {
            return 0;
        }
        $kind = $post[self::KIND] ?? null;

        return (is_string($kind) ? CodKind::tryFrom($kind) : null)?->flag() ?? CodKind::Money->flag();
    }
}
