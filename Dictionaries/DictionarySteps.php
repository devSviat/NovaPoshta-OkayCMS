<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Cdn\CdnVersions;

/** The work a dictionary run is made of; DictionaryRunner decides which steps run. */
interface DictionarySteps
{
    public function siteHasEnglish(): bool;

    public function divisionVersions(string $lang): CdnVersions;

    public function settlementVersions(string $lang): CdnVersions;

    public function fullDivisions(string $ukUrl, ?string $enUrl, int $run): UpdateCounts;

    public function deltaDivisions(string $ukUrl, ?string $enUrl, int $run): UpdateCounts;

    public function fullSettlements(string $ukUrl, ?string $enUrl, int $run): UpdateCounts;

    /** @return int settlements with non-zero popularity */
    public function boost(): int;
}
