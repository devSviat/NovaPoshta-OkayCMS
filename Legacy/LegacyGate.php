<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Whether a migration may run now; a run before "Update" or before the dictionaries leaves damage a re-run cannot repair. */
interface LegacyGate
{
    /** @return string|null language key of what is missing, null when ready */
    public function problem(): ?string;
}
