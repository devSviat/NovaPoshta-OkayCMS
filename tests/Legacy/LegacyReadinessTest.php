<?php

namespace Modules\Sviat\NovaPoshta\Legacy;

use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReadiness;
use PHPUnit\Framework\TestCase;

class LegacyReadinessTest extends TestCase
{
    public function testUpdateFirstThenDictionaries(): void
    {
        self::assertSame('legacy_needs_update', LegacyReadiness::problemOf(false, 0, 0));
        self::assertSame('legacy_needs_dictionaries', LegacyReadiness::problemOf(true, 0, 27000));
        self::assertSame('legacy_needs_dictionaries', LegacyReadiness::problemOf(true, 50000, 0));
        self::assertNull(LegacyReadiness::problemOf(true, 50000, 27000));
    }
}
