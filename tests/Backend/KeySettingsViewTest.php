<?php

namespace Modules\Sviat\NovaPoshta\Backend;

require_once __DIR__ . '/../Support/ApiFixtures.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Okay\Modules\Sviat\NovaPoshta\Backend\KeySettingsView;
use Okay\Modules\Sviat\NovaPoshta\Backend\KeySubmission;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyAccess as A;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyCheckResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KeySettingsViewTest extends TestCase
{
    private static function checkResult(A $new, A $old): KeyCheckResult
    {
        return new KeyCheckResult($new, $old, new \DateTimeImmutable('2026-11-06 08:42:00 UTC'));
    }

    public function testFullKeyNeverReachesTheTemplate(): void
    {
        $vars = KeySettingsView::vars(F::KEY, self::checkResult(A::Ok, A::Ok), null);

        self::assertStringNotContainsString(F::KEY, json_encode($vars, JSON_UNESCAPED_UNICODE));
        self::assertSame('••••1e70', $vars['key_masked']);
    }

    public function testNoKey(): void
    {
        $vars = KeySettingsView::vars(null, null, null);

        self::assertSame('none', $vars['state']);
        self::assertFalse($vars['has_key']);
        self::assertSame([], $vars['rows']);
    }

    public function testSavedKeyNeverCheckedLooksUnchecked(): void
    {
        self::assertSame('unchecked', KeySettingsView::vars(F::KEY, null, null)['state']);
    }

    public static function states(): array
    {
        return [
            'ok' => [A::Ok, A::Ok, 'ok', ['ok', 'ok']],
            'invalid' => [A::InvalidKey, A::InvalidKey, 'invalid', ['invalid_key', 'invalid_key']],
            'denied' => [A::Denied, A::Ok, 'denied', ['denied', 'ok']],
            'unreachable' => [A::Unreachable, A::Unreachable, 'unreachable', ['unreachable', 'unreachable']],
            'invalid wins over unreachable' => [A::Unreachable, A::InvalidKey, 'invalid', ['unreachable', 'invalid_key']],
            'denied wins over unreachable' => [A::Denied, A::Unreachable, 'denied', ['denied', 'unreachable']],
        ];
    }

    #[DataProvider('states')]
    public function testStateFromTheLastResult(A $new, A $old, string $state, array $rows): void
    {
        $vars = KeySettingsView::vars(F::KEY, self::checkResult($new, $old), null);

        self::assertSame($state, $vars['state']);
        self::assertSame($rows, array_column($vars['rows'], 'access'));
        self::assertSame(['new_api', 'old_api'], array_column($vars['rows'], 'api'));
    }

    public function testSubmissionWinsOverTheStoredResult(): void
    {
        $submission = new KeySubmission(self::checkResult(A::InvalidKey, A::InvalidKey), saved: false);
        $vars = KeySettingsView::vars(F::OTHER_KEY, self::checkResult(A::Ok, A::Ok), $submission);

        self::assertSame('invalid', $vars['state']);
        self::assertTrue($vars['kept_previous'], 'a working key was kept');
    }

    public function testCheckedAtIsShownInTheShopTimezone(): void
    {
        $vars = KeySettingsView::vars(F::KEY, self::checkResult(A::Ok, A::Ok), null, new \DateTimeZone('Europe/Kyiv'));

        self::assertSame('06.11.2026 10:42', $vars['checked_at']);
    }
}
