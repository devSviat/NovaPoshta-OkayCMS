<?php

namespace Modules\Sviat\NovaPoshta\Api;

use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ApiKeyTest extends TestCase
{
    private const string KEY = 'test-np-key-not-real-aaaaaaaaaaa';

    public static function pasted(): array
    {
        return [
            'spaces and newline' => [" TEST-NP-KEY-NOT-REAL-AAAAAAAAAAA\n", 'TEST-NP-KEY-NOT-REAL-AAAAAAAAAAA'],
            'double quotes' => ['"' . self::KEY . '"', self::KEY],
            'single quotes and tab' => ["\t'" . self::KEY . "' ", self::KEY],
            'clean' => [self::KEY, self::KEY],
            'nbsp and zero-width space' => ["\u{00A0}" . self::KEY . "\u{200B}", self::KEY],
            'BOM' => ["\u{FEFF}" . self::KEY, self::KEY],
            'guillemets' => ['«' . self::KEY . '»', self::KEY],
            'smart quotes' => ['“' . self::KEY . '”', self::KEY],
        ];
    }

    #[DataProvider('pasted')]
    public function testPastedKeyIsNormalized(string $raw, string $expected): void
    {
        self::assertSame($expected, ApiKey::normalize($raw));
    }

    public function testMaskedShowsOnlyLastFour(): void
    {
        self::assertSame('••••aaaa', ApiKey::masked(self::KEY));
    }

    public function testFingerprintDoesNotRevealTheKey(): void
    {
        $fingerprint = ApiKey::fingerprint(self::KEY);

        self::assertStringNotContainsString(self::KEY, $fingerprint);
        self::assertSame($fingerprint, ApiKey::fingerprint(strtoupper(self::KEY)));
        self::assertNotSame($fingerprint, ApiKey::fingerprint(str_repeat('0', 32)));
    }

    public function testValueComesFromSettings(): void
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnMap([[ApiKey::SETTING, ' ' . self::KEY . ' ']]);

        self::assertSame(self::KEY, new ApiKey($settings)->value());
    }

    public function testMissingKeyIsNull(): void
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturn(null);

        self::assertNull(new ApiKey($settings)->value());
    }
}
