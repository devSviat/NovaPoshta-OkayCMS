<?php

namespace Modules\Sviat\NovaPoshta\Legacy;

use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReadiness;
use PHPUnit\Framework\TestCase;

class LegacyReadinessTest extends TestCase
{
    public function testDictionariesFirst(): void
    {
        self::assertSame('legacy_needs_dictionaries', LegacyReadiness::problemOf(0, 27000));
        self::assertSame('legacy_needs_dictionaries', LegacyReadiness::problemOf(50000, 0));
        self::assertNull(LegacyReadiness::problemOf(50000, 27000));
    }
}
