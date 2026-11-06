<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api;

use Okay\Core\Settings;

/** The shop's Nova Poshta API key from the module settings. Never log or render it unmasked. */
final class ApiKey
{
    public const string SETTING = 'sviat__novaposhta__api_key';

    public function __construct(private readonly Settings $settings)
    {
    }

    public function value(): ?string
    {
        $raw = $this->settings->get(self::SETTING);
        $key = is_string($raw) ? self::normalize($raw) : '';

        return $key === '' ? null : $key;
    }

    /** Strips what copying from the business cabinet or a messenger tends to add. */
    public static function normalize(string $raw): string
    {
        return trim($raw, " \t\n\r\0\x0B\"'");
    }

    /** Business-cabinet keys are 32 hex characters. */
    public static function isWellFormed(string $key): bool
    {
        return preg_match('/^[0-9a-f]{32}$/i', $key) === 1;
    }

    /** Ties cached data (the JWT) to the key without storing the key next to it. */
    public static function fingerprint(string $key): string
    {
        return hash('sha256', strtolower($key));
    }

    public static function masked(string $key): string
    {
        return '••••' . substr($key, -4);
    }
}
