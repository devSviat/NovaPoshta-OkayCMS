<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Перенесено з NovaPoshtaTracking NovaPoshtaStatusHelperTest. */
class WaybillStatusTest extends TestCase
{
    #[DataProvider('known')]
    public function testKnownCodesGetTextAndGroup(int $code, string $group): void
    {
        self::assertSame($group, WaybillStatus::group($code));
        self::assertSame('status_' . ($code === 41 ? 4 : $code), WaybillStatus::textKey($code));
    }

    public static function known(): array
    {
        return [
            [1, 'created'], [2, 'deleted'], [3, 'not-found'], [4, 'in-transit'], [41, 'in-transit'], [5, 'in-transit'], [6, 'in-transit'], [101, 'in-transit'],
            [7, 'arrived'], [8, 'arrived'], [9, 'received'], [10, 'received'], [11, 'received'], [12, 'processing'],
            [102, 'refused'], [103, 'refused'], [104, 'changed'], [105, 'changed'], [111, 'changed'], [112, 'changed'], [106, 'return'],
        ];
    }

    public function testUnknownCodeStillRendersSomething(): void
    {
        self::assertSame('failed', WaybillStatus::group(777));
        self::assertSame('status_unknown', WaybillStatus::textKey(777));
    }

    public function testMissingStatusGivesNull(): void
    {
        self::assertNull(WaybillStatus::group(null));
        self::assertNull(WaybillStatus::group(0));
    }

    public function testCityOfDepartureCodesAreEquivalent(): void
    {
        self::assertSame(WaybillStatus::group(4), WaybillStatus::group(41));
        self::assertSame(WaybillStatus::textKey(4), WaybillStatus::textKey(41));
    }

    public function testFinalStatuses(): void
    {
        foreach ([2, 9, 11, 102, 103, 106] as $code) {
            self::assertTrue(WaybillStatus::isFinal($code), (string)$code);
        }
        foreach ([1, 7, 10, 12, 3, null] as $code) {
            self::assertFalse(WaybillStatus::isFinal($code), (string)$code);
        }
    }

    public function testFilterGroupsCoverEveryKnownCode(): void
    {
        $codes = array_merge(...array_values(WaybillStatus::FILTER_GROUPS));
        foreach (self::known() as [$code]) {
            self::assertContains($code, $codes, (string)$code);
        }
        self::assertSame([], WaybillStatus::FILTER_GROUPS['none']);
    }
}
