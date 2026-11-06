<?php

namespace Okay\Modules\Sviat\NovaPoshta\Backend;

use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyCheckResult;

final readonly class KeySubmission
{
    public function __construct(
        public KeyCheckResult $result,
        public bool $saved,
        /** The saved key was checked again; nothing new was entered. */
        public bool $recheck = false,
    ) {
    }
}
