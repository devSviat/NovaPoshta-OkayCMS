<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/**
 * Коди статусів накладних Нової Пошти (однакові в обох API), згруповані для бейджів і фільтра
 * замовлень. Групи перенесено з NovaPoshtaTracking NovaPoshtaStatusHelper.
 */
final class WaybillStatus
{
    /** З посилкою більше нічого не станеться; 10 (отримано, грошовий переказ очікується) не фінальний. */
    public const array FINAL = [2, 9, 11, 102, 103, 106];

    /** Більше не опитується: для посилки не фінальний, але дізнаватися нічого (номер невідомий 30 днів). */
    public const int NOT_FOUND = 3;
    /** НП видаляє накладну лише до передачі: статусу ще немає або «Створено» (1); «Видалено» (2) лише забувається. */
    public const array DELETABLE = [1, 2];

    private const array GROUPS = [
        'created' => [1],
        'deleted' => [2],
        'not-found' => [3],
        'in-transit' => [4, 41, 5, 6, 101],
        'arrived' => [7, 8],
        'received' => [9, 10, 11],
        'processing' => [12],
        'refused' => [102, 103],
        'changed' => [104, 105, 111, 112],
        'return' => [106],
    ];

    /** Групи фільтра списку замовлень; `none` — замовлення без накладної. */
    public const array FILTER_GROUPS = [
        'created' => [1, 12],
        'in_transit' => [4, 41, 5, 6, 101],
        'arrived' => [7, 8],
        'received' => [9, 10, 11],
        'refused' => [102, 103],
        'changed' => [104, 105, 111, 112],
        'return' => [106],
        'deleted' => [2],
        'not_found' => [3],
        'none' => [],
    ];

    public static function group(?int $code): ?string
    {
        if (!$code) {
            return null;
        }
        foreach (self::GROUPS as $group => $codes) {
            if (in_array($code, $codes, true)) {
                return $group;
            }
        }

        return 'failed';
    }

    public static function isFinal(?int $code): bool
    {
        return $code !== null && in_array($code, self::FINAL, true);
    }

    /** Мовний ключ тексту статусу; 41 читається як 4 (обидва: у місті відправлення). */
    public static function textKey(int $code): string
    {
        $code = $code === 41 ? 4 : $code;
        foreach (self::GROUPS as $codes) {
            if (in_array($code, $codes, true)) {
                return 'status_' . $code;
            }
        }

        return 'status_unknown';
    }
}
