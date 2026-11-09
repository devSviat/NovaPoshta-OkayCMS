<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryCounts;

final class LegacyReadiness implements LegacyGate
{
    public function __construct(private readonly DictionaryCounts $counts)
    {
    }

    public function problem(): ?string
    {
        $summary = $this->counts->summary($this->counts->byCategory());

        return self::problemOf((int)$summary['divisions'], (int)$summary['settlements']);
    }

    public static function problemOf(int $divisions, int $settlements): ?string
    {
        return $divisions > 0 && $settlements > 0 ? null : 'legacy_needs_dictionaries';
    }
}
