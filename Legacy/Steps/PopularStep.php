<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy\Steps;

use Okay\Modules\Sviat\NovaPoshta\Cart\PopularCities;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyBudget;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyCityMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyData;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReport;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyTargets;

/** PopularCities' list, only into an empty list of the module: a list the manager made wins. */
final class PopularStep implements LegacyStep
{
    public const string NAME = 'popular';

    public function __construct(
        private readonly LegacyData $old,
        private readonly LegacyTargets $ours,
        private readonly LegacyCityMatcher $cities,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(int $cursor, LegacyBudget $budget, LegacyReport $report): int
    {
        $old = $this->old->popularCities();
        if ($old === []) {
            return self::DONE;
        }
        if ($this->ours->popularIds() !== []) {
            foreach ($old as $ignored) {
                $report->add(self::NAME, 'existed');
            }

            return self::DONE;
        }
        $ids = [];
        $fails = [];
        foreach (array_slice($old, 0, PopularCities::MAX) as $city) {
            $name = trim((string)($city->name ?? ''));
            [$id, $why] = $this->cities->match((string)$city->city_ref, $name !== '' ? $name : null, $budget);
            if ($why === LegacyCityMatcher::BUDGET) {
                // Nothing written yet: the whole list is tried again next tick.
                return $cursor;
            }
            if ($id !== null) {
                $ids[$id] = $id;
            } else {
                $fails[] = $name !== '' ? $name : (string)$city->city_ref;
            }
        }
        $this->ours->savePopular(array_values($ids));
        foreach ($ids as $ignored) {
            $report->add(self::NAME, 'done');
        }
        foreach ($fails as $name) {
            $report->fail(self::NAME, null, 'legacy_fail_namesakes_popular', $name);
        }

        return self::DONE;
    }
}
