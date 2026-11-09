<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use PHPUnit\Framework\TestCase;

final class ApiFixtures
{
    public const string KEY = '76a0a855c068a51254fb19d539fa1e70';
    public const string OTHER_KEY = '0123456789abcdef0123456789abcdef';

    /** Непідписаний JWT із заданим терміном дії; модуль читає лише `exp`. */
    public static function jwt(int $exp, string $sub = 'shop'): string
    {
        $part = static fn(array $data): string => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $part(['alg' => 'HS256', 'typ' => 'JWT']) . '.' . $part(['sub' => $sub, 'exp' => $exp]) . '.c2ln';
    }

    /** ApiKey, чиє налаштування можна змінити під час тесту через повернене посилання. */
    public static function apiKey(TestCase $test, ?string &$value): ApiKey
    {
        $settings = (new \ReflectionMethod($test, 'createStub'))->invoke($test, Settings::class);
        $settings->method('get')->willReturnCallback(static function (string $name) use (&$value) {
            return $name === ApiKey::SETTING ? $value : null;
        });

        return new ApiKey($settings);
    }
}
