<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/**
 * Рядок накладної, зарезервований до запиту до Нової Пошти: він не має номера (і `api`), доки
 * створення не вдасться, і ніколи не показується й не відстежується як накладна.
 */
final class PendingWaybill
{
    public static function is(?object $row): bool
    {
        return $row !== null && ($row->number ?? null) === null;
    }
}
