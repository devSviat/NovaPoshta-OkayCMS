<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/**
 * Тиждень пункту видачі так, як його показує кошик: дні з однаковими годинами в одній групі ("Пн–Пт 08:00–21:00").
 * День тижня, а не "сьогодні": посилку забирають через кілька днів після оформлення замовлення.
 */
final class WorkSchedule
{
    private const array DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * @param string $json `schedule` з довідника: workSchedule Нової Пошти, [{day, from, to, …}]
     * @return list<array{from:int, to:int, hours:?string}>|list<array{allDay:true}>|null дні 0 = понеділок … 6 = неділя;
     *         hours null — вихідний; null, коли розкладу немає або його не вдається прочитати
     */
    public static function groups(string $json): ?array
    {
        $rows = json_decode($json, true);
        if (!is_array($rows) || $rows === []) {
            return null;
        }
        $hours = array_fill(0, 7, null);
        foreach ($rows as $row) {
            $index = is_array($row) ? array_search($row['day'] ?? null, self::DAYS, true) : false;
            $from = (string)($row['from'] ?? '');
            $to = (string)($row['to'] ?? '');
            // Нова Пошта записує вихідний день як "-".
            if ($index !== false && self::isTime($from) && self::isTime($to)) {
                $hours[$index] = [$from, $to];
            }
        }
        if (array_filter($hours, static fn(?array $h): bool => $h !== null && $h[0] <= '00:01' && $h[1] >= '23:59') === $hours) {
            return [['allDay' => true]];
        }

        $groups = [];
        foreach ($hours as $day => $pair) {
            $text = $pair === null ? null : $pair[0] . '–' . $pair[1];
            $last = array_key_last($groups);
            if ($last !== null && $groups[$last]['hours'] === $text) {
                $groups[$last]['to'] = $day;
            } else {
                $groups[] = ['from' => $day, 'to' => $day, 'hours' => $text];
            }
        }

        return $groups;
    }

    private static function isTime(string $value): bool
    {
        return preg_match('/^([01]\d|2[0-4]):[0-5]\d$/', $value) === 1;
    }
}
