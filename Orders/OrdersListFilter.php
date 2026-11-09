<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillNumber;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStatus;

/**
 * Фільтр списку замовлень із частинами цього модуля: група статусів НП і ключове слово, яке є
 * номером накладної. Модуль не може розширити фільтр ядра `keyword` (фільтр модуля замінює його
 * повністю), тому номер накладної перетворюється на список id замовлень.
 */
final class OrdersListFilter
{
    /** @param callable(string): list<int> $ordersOfNumber */
    public static function apply(array $filter, ?string $group, ?string $keyword, callable $ordersOfNumber): array
    {
        $number = is_string($keyword) && preg_match('/^[\d\s\-]+$/', trim($keyword)) ? WaybillNumber::normalize(trim($keyword)) : null;
        $byNumber = $number !== null ? $ordersOfNumber($number) : [];
        // Не накладна цього магазину: пошук ядра лишає його (штрихкод у назві товару, цифри в email).
        if ($byNumber !== []) {
            unset($filter['keyword']);
            $filter = self::narrow($filter, $byNumber);
        }
        if ($group === 'none') {
            $filter['np_without_waybill'] = 1;
        } elseif ($group !== null && $group !== '' && isset(WaybillStatus::FILTER_GROUPS[$group])) {
            // Підзапит (OrdersEntityFilters), а не id кожного замовлення групи.
            $filter['np_status_codes'] = WaybillStatus::FILTER_GROUPS[$group];
        }

        return $filter;
    }

    /** @param list<int> $ids */
    private static function narrow(array $filter, array $ids): array
    {
        if (isset($filter['id'])) {
            $ids = array_values(array_intersect($ids, array_map(intval(...), (array)$filter['id'])));
        }
        // Порожній список означав би для фільтра ядра "без умови": [0] не збігається ні з чим.
        $filter['id'] = $ids === [] ? [0] : array_values(array_unique($ids));

        return $filter;
    }
}
