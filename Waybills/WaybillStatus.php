<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/**
 * Nova Poshta waybill status codes (the same in both APIs), grouped for badges and the orders
 * filter. Groups ported from NovaPoshtaTracking NovaPoshtaStatusHelper.
 */
final class WaybillStatus
{
    /** Nothing more will happen to the parcel; 10 (received, money transfer pending) is not final. */
    public const array FINAL = [2, 9, 11, 102, 103, 106];

    /** Not polled any more: not final for the parcel, but nothing to learn (number unknown for 30 days). */
    public const int NOT_FOUND = 3;

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

    /** Groups of the orders list filter; `none` — orders without a waybill. */
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

    /** Language key of the status text; 41 reads as 4 (both: in the city of departure). */
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
