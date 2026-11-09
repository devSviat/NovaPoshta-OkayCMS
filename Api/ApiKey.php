<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api;

use Okay\Core\Settings;

/** Ключ API Нової Пошти магазину з налаштувань модуля. Ніколи не логувати й не виводити його без маски. */
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

    /**
     * Прибирає те, що зазвичай додає копіювання з бізнес-кабінету, месенджера чи документа:
     * будь-які пробіли Unicode, невидимі символи форматування (BOM, zero-width) і лапки будь-якого стилю.
     */
    public static function normalize(string $raw): string
    {
        $edge = '[\s\p{Z}\p{Cf}"\'«»“”„‘’]+';

        return preg_replace("/^{$edge}|{$edge}$/u", '', $raw) ?? trim($raw);
    }

    /** Прив'язує кешовані дані (токен JWT) до ключа, не зберігаючи ключ поруч із ними. */
    public static function fingerprint(string $key): string
    {
        return hash('sha256', strtolower($key));
    }

    public static function masked(string $key): string
    {
        return '••••' . substr($key, -4);
    }
}
