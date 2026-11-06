<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Core\EntityFactory;
use Okay\Entities\LanguagesEntity;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Cdn\CdnClient;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Cdn\CdnVersions;

/** Dictionary steps on real CDN files: each file is downloaded, applied and deleted. */
final class CdnDictionarySteps implements DictionarySteps
{
    public function __construct(
        private readonly CdnClient $cdn,
        private readonly DivisionsUpdater $divisions,
        private readonly SettlementsUpdater $settlements,
        private readonly BoostUpdater $boost,
        private readonly EntityFactory $entityFactory,
    ) {
    }

    public function siteHasEnglish(): bool
    {
        foreach ($this->entityFactory->get(LanguagesEntity::class)->find() as $language) {
            if ($language->label === 'en') {
                return true;
            }
        }

        return false;
    }

    public function divisionVersions(string $lang): CdnVersions
    {
        return $this->cdn->divisionVersions($lang);
    }

    public function settlementVersions(string $lang): CdnVersions
    {
        return $this->cdn->settlementVersions($lang);
    }

    public function fullDivisions(string $ukUrl, ?string $enUrl, int $run): UpdateCounts
    {
        return $this->withFiles($ukUrl, $enUrl, fn(string $uk, ?string $en) => $this->divisions->full($uk, $en, $run));
    }

    public function deltaDivisions(string $ukUrl, ?string $enUrl, int $run): UpdateCounts
    {
        return $this->withFiles($ukUrl, $enUrl, fn(string $uk, ?string $en) => $this->divisions->delta($uk, $en, $run));
    }

    public function fullSettlements(string $ukUrl, ?string $enUrl, int $run): UpdateCounts
    {
        return $this->withFiles($ukUrl, $enUrl, fn(string $uk, ?string $en) => $this->settlements->full($uk, $en, $run));
    }

    public function boost(): int
    {
        return $this->boost->update();
    }

    private function withFiles(string $ukUrl, ?string $enUrl, \Closure $apply): UpdateCounts
    {
        $files = [];
        try {
            $files[] = $uk = $this->cdn->download($ukUrl);
            $en = null;
            if ($enUrl !== null) {
                $files[] = $en = $this->cdn->download($enUrl);
            }

            return $apply($uk, $en);
        } finally {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }
}
