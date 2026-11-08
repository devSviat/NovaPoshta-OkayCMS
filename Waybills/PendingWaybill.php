<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/**
 * A waybill row reserved before Nova Poshta is asked: it has no number (and no `api`) until the
 * create succeeds, and is never shown or tracked as a waybill.
 */
final class PendingWaybill
{
    public static function is(?object $row): bool
    {
        return $row !== null && ($row->number ?? null) === null;
    }
}
