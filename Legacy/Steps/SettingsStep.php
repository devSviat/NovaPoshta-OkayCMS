<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy\Steps;

use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyBudget;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyCityMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyData;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyDivisionMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyNames;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReport;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacySettingsMap;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyTargets;

/** Old settings only where the module has none; the sender's point goes through the same matching as orders. */
final class SettingsStep implements LegacyStep
{
    public const string NAME = 'settings';

    public function __construct(
        private readonly LegacyData $old,
        private readonly LegacyTargets $ours,
        private readonly LegacyCityMatcher $cities,
        private readonly LegacyDivisionMatcher $divisions,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(int $cursor, LegacyBudget $budget, LegacyReport $report): int
    {
        $sender = $this->sender($budget);
        if ($sender === LegacyCityMatcher::BUDGET) {
            return $cursor;
        }
        $values = LegacySettingsMap::values(fn(string $n): ?string => $this->old->setting($n), fn(string $n): ?string => $this->ours->ourSetting($n));
        foreach ($values['set'] as $name => $value) {
            $this->ours->setOurSetting($name, $value);
            $report->add(self::NAME, 'done');
        }
        foreach ($values['existed'] as $ignored) {
            $report->add(self::NAME, 'existed');
        }
        foreach ($values['skipped'] as $name) {
            $report->fail(self::NAME, null, 'legacy_fail_setting', $name);
        }
        if ($sender === 'existed') {
            $report->add(self::NAME, 'existed');
        } elseif ($sender === 'failed') {
            $report->fail(self::NAME, null, 'legacy_fail_sender');
        } elseif ($sender !== null) {
            $this->ours->setOurSetting(ShippingSettings::SENDER, $sender);
            $report->add(self::NAME, 'done');
        }

        return self::DONE;
    }

    /** @return ?string our division id, 'existed', 'failed', BUDGET, or null when there is nothing to carry */
    private function sender(LegacyBudget $budget): ?string
    {
        $ref = $this->old->setting('novapost_sender_warehouse');
        if ($ref === null) {
            return null;
        }
        if ((int)$this->ours->ourSetting(ShippingSettings::SENDER) > 0) {
            return 'existed';
        }
        $warehouse = $this->old->warehouse($ref);
        if ($warehouse === null) {
            return 'failed';
        }
        [$settlementId, $why] = $this->cities->match((string)$warehouse->city_ref, $this->old->cityName((string)$warehouse->city_ref), $budget);
        if ($why === LegacyCityMatcher::BUDGET) {
            return LegacyCityMatcher::BUDGET;
        }
        $name = (string)($warehouse->name ?? '');
        $division = $settlementId !== null ? $this->divisions->match($settlementId, LegacyNames::number($warehouse->number ?? null, $name), $name) : null;

        return $division !== null ? (string)$division->id : 'failed';
    }
}
