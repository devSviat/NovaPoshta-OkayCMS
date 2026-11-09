<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

interface RecipientCities
{
    /** Ref міста старого API для населеного пункту; null, коли Нова Пошта його не знає або не відповідає. */
    public function cityRef(int $settlementId): ?string;
}
