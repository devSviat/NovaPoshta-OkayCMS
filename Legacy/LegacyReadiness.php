<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryCounts;
use Okay\Modules\Sviat\NovaPoshta\Init\Init;

final class LegacyReadiness implements LegacyGate
{
    public function __construct(private readonly DictionaryCounts $counts)
    {
    }

    public function problem(): ?string
    {
        $summary = $this->counts->summary($this->counts->byCategory());

        return self::problemOf(Init::schemaAtLeast('0.11.0'), (int)$summary['divisions'], (int)$summary['settlements']);
    }

    public static function problemOf(bool $schemaReady, int $divisions, int $settlements): ?string
    {
        if (!$schemaReady) {
            // Without the 0.11.0 column an unmatched order would lose its warning for good.
            return 'legacy_needs_update';
        }

        return $divisions > 0 && $settlements > 0 ? null : 'legacy_needs_dictionaries';
    }
}
