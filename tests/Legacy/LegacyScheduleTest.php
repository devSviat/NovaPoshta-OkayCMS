<?php

namespace Modules\Sviat\NovaPoshta\Legacy;

use Okay\Modules\Sviat\NovaPoshta\Init\Init;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyMigration;
use PHPUnit\Framework\TestCase;

/** Перенесення запускається щохвилини, а один запуск коротший за хвилину, тож запуски не накладаються. */
class LegacyScheduleTest extends TestCase
{
    public function testMigrationRunsEveryMinute(): void
    {
        $source = (string)file_get_contents((new \ReflectionClass(Init::class))->getFileName());

        self::assertMatchesRegularExpression("~\[LegacyMigration::class, 'tick'\]\)\s*->name\('Nova Poshta: data from the old modules'\)\s*->time\('\* \* \* \* \*'\)\s*->overlap\(false\)~", $source);
    }

    public function testOneTickFitsInAMinute(): void
    {
        $seconds = (new \ReflectionClassConstant(LegacyMigration::class, 'TICK_SECONDS'))->getValue();

        self::assertLessThan(60.0, $seconds);
    }
}
