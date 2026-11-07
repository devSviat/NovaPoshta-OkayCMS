<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillNumber;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStatus;

/**
 * The orders list filter with this module's parts: the NP status group, and a keyword that is a
 * waybill number. A module cannot extend the core `keyword` filter (a module filter replaces it
 * whole), so a waybill number becomes a list of order ids instead.
 */
final class OrdersListFilter
{
    /**
     * @param callable(string): list<int> $ordersOfNumber
     * @param callable(list<int>): list<int> $ordersWithCodes
     */
    public static function apply(array $filter, ?string $group, ?string $keyword, callable $ordersOfNumber, callable $ordersWithCodes): array
    {
        $number = is_string($keyword) && preg_match('/^[\d\s\-]+$/', trim($keyword)) ? WaybillNumber::normalize(trim($keyword)) : null;
        $byNumber = $number !== null ? $ordersOfNumber($number) : [];
        // Not a waybill of this shop: the core search keeps it (a barcode in a product name, digits in an email).
        if ($byNumber !== []) {
            unset($filter['keyword']);
            $filter = self::narrow($filter, $byNumber);
        }
        if ($group === 'none') {
            $filter['np_without_waybill'] = 1;
        } elseif ($group !== null && $group !== '' && isset(WaybillStatus::FILTER_GROUPS[$group])) {
            $filter = self::narrow($filter, $ordersWithCodes(WaybillStatus::FILTER_GROUPS[$group]));
        }

        return $filter;
    }

    /** @param list<int> $ids */
    private static function narrow(array $filter, array $ids): array
    {
        if (isset($filter['id'])) {
            $ids = array_values(array_intersect($ids, array_map('intval', (array)$filter['id'])));
        }
        // An empty list would mean "no condition" to the core filter: [0] matches nothing.
        $filter['id'] = $ids === [] ? [0] : array_values(array_unique($ids));

        return $filter;
    }
}
