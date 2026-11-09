<?php

namespace Okay\Modules\Sviat\NovaPoshta\Backend;

use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyCheckResult;

final readonly class KeySubmission
{
    public function __construct(
        public KeyCheckResult $result,
        public bool $saved,
        /** Збережений ключ перевірено ще раз; нового нічого не введено. */
        public bool $recheck = false,
    ) {
    }
}
