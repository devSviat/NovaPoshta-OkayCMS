<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Один вид старих даних. Запуск іде від курсора (останній оброблений id джерела), доки не скінчиться крок або бюджет. */
interface LegacyStep
{
    public const int DONE = -1;

    public function name(): string;

    /** @return int новий курсор або DONE */
    public function run(int $cursor, LegacyBudget $budget, LegacyReport $report): int;
}
