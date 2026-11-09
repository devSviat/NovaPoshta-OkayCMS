<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Cdn\CdnVersions;

/** Робота, з якої складається запуск довідників; DictionaryRunner вирішує, які кроки виконуються. */
interface DictionarySteps
{
    public function siteHasEnglish(): bool;

    public function divisionVersions(string $lang): CdnVersions;

    public function settlementVersions(string $lang): CdnVersions;

    public function fullDivisions(string $ukUrl, ?string $enUrl, int $run): UpdateCounts;

    public function deltaDivisions(string $ukUrl, ?string $enUrl, int $run): UpdateCounts;

    public function fullSettlements(string $ukUrl, ?string $enUrl, int $run): UpdateCounts;

    /** @return int населені пункти з ненульовою популярністю */
    public function boost(): int;
}
