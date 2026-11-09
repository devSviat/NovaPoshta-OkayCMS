<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy\Steps;

use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyBudget;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyData;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReport;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyTargets;

/** `volume` варіанта з NovaposhtaCost — у власне поле модуля, там, де воно ще порожнє. */
final class VolumesStep implements LegacyStep
{
    public const string NAME = 'volumes';
    private const int BATCH = 500;

    public function __construct(private readonly LegacyData $old, private readonly LegacyTargets $ours)
    {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(int $cursor, LegacyBudget $budget, LegacyReport $report): int
    {
        $rows = $this->old->volumes($cursor, self::BATCH);
        foreach ($rows as $row) {
            if (!$budget->hasTime()) {
                return $cursor;
            }
            $cursor = (int)$row->id;
            $ours = $this->ours->variantVolume($cursor);
            if ($ours === null) {
                continue;
            }
            if ($ours > 0) {
                $report->add(self::NAME, 'existed');
            } else {
                $this->ours->setVariantVolume($cursor, round((float)$row->volume, 5));
                $report->add(self::NAME, 'done');
            }
        }

        return count($rows) < self::BATCH ? self::DONE : $cursor;
    }
}
