<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** One kind of old data. A run goes from the cursor (last source id done) until the step or the budget ends. */
interface LegacyStep
{
    public const int DONE = -1;

    public function name(): string;

    /** @return int the new cursor, or DONE */
    public function run(int $cursor, LegacyBudget $budget, LegacyReport $report): int;
}
