<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

interface SenderSource
{
    /** @return SenderParty|string the sender, or the language key of what is missing */
    public function party(): SenderParty|string;
}
