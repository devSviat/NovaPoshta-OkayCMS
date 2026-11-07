<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

interface RecipientCities
{
    /** Old API city Ref of a settlement; null when Nova Poshta does not know it or does not answer. */
    public function cityRef(int $settlementId): ?string;
}
