<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

interface SenderSource
{
    /** @return SenderParty|string відправник або мовний ключ того, чого бракує */
    public function party(): SenderParty|string;
}
