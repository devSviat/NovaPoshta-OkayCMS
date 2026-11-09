<?php

namespace Okay\Modules\Sviat\NovaPoshta\Payments;

use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;

/** Блок "Nova Poshta cash on delivery" на сторінці способу оплати ядра (payment_cod.tpl). */
final class CodPaymentInput
{
    /** Приховане поле блоку: без нього POST прийшов з іншого місця, і колонку лишаємо як є. */
    public const string MARKER = 'sviat_np_cod_form';
    public const string SWITCH = 'sviat_np_cod';
    public const string KIND = 'sviat_np_cod_kind';

    /**
     * @param array<string, mixed> $post
     * @param mixed $module надісланий модуль оплати; онлайн-оплата ніколи не є післяплатою
     * @return int|null прапорець CodKind для збереження або null, щоб не чіпати колонку
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
