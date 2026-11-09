<?php

namespace Modules\Sviat\NovaPoshta\Api;

use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use PHPUnit\Framework\TestCase;

class NpApiExceptionTest extends TestCase
{
    public function testMessageNeverContainsSecrets(): void
    {
        $key = 'fedcba9876543210fedcba9876543210';
        $jwt = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.c2lnbmF0dXJl';

        $e = NpApiException::fromNp(
            NpErrorKind::Rejected,
            ["API key {$key} expired", "token {$jwt}"],
            ['20000201204'],
            secrets: [$key, $jwt],
        );

        self::assertStringNotContainsString($key, $e->getMessage());
        self::assertStringNotContainsString($jwt, $e->getMessage());
        self::assertStringContainsString('***', $e->getMessage());
        self::assertSame(['20000201204'], $e->codes);
        self::assertSame(NpErrorKind::Rejected, $e->kind);
        foreach ($e->npMessages as $text) {
            self::assertStringNotContainsString($key, $text);
        }
    }

    public function testEmptySecretsAreIgnored(): void
    {
        $e = NpApiException::fromNp(NpErrorKind::Unavailable, ['timeout'], [], secrets: ['', null]);

        self::assertSame('timeout', $e->getMessage());
    }
}
