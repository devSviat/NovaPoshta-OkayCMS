<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Cdn\CdnVersions;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionarySteps;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\UpdateCounts;

/** Записує, що вирішив зробити раннер; версії й збої задає тест. */
final class RecordingSteps implements DictionarySteps
{
    /** @var list<string> */
    public array $calls = [];
    public bool $english = false;
    /** @var array<string, CdnVersions> 'divisions:uk' → версії */
    public array $versions = [];
    /** @var array<string, \Throwable> назва кроку → збій */
    public array $failures = [];
    public int $boosted = 5;
    public ?\Closure $onStep = null;

    public function siteHasEnglish(): bool
    {
        return $this->english;
    }

    public function divisionVersions(string $lang): CdnVersions
    {
        return $this->versions["divisions:{$lang}"];
    }

    public function settlementVersions(string $lang): CdnVersions
    {
        return $this->versions["settlements:{$lang}"];
    }

    public function fullDivisions(string $ukUrl, ?string $enUrl, int $run): UpdateCounts
    {
        return $this->step("fullDivisions {$ukUrl} " . ($enUrl ?? '-'), new UpdateCounts(added: 10));
    }

    public function deltaDivisions(string $ukUrl, ?string $enUrl, int $run): UpdateCounts
    {
        return $this->step("deltaDivisions {$ukUrl} " . ($enUrl ?? '-'), new UpdateCounts(changed: 2));
    }

    public function fullSettlements(string $ukUrl, ?string $enUrl, int $run): UpdateCounts
    {
        return $this->step("fullSettlements {$ukUrl} " . ($enUrl ?? '-'), new UpdateCounts(added: 3));
    }

    public function boost(): int
    {
        return $this->step('boost', $this->boosted);
    }

    private function step(string $call, mixed $result): mixed
    {
        $this->calls[] = $call;
        if ($this->onStep) {
            ($this->onStep)();
        }
        $name = strtok($call, ' ');
        if (isset($this->failures[$name])) {
            throw $this->failures[$name];
        }

        return $result;
    }
}
