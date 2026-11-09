<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Waybills\NpPhone;

/** Старі налаштування, яким є місце в модулі, перевірені за тими самими правилами, що й форми модуля. */
final class LegacySettingsMap
{
    private const string WAYBILL = 'sviat__novaposhta__waybill_';
    private const array MAP = [
        // Ключ не перевіряється: чи він працює, відповідає Нова Пошта, як і на сторінці налаштувань.
        [ApiKey::SETTING, 'newpost_key', 'any'],
        [ShippingSettings::WEIGHT, 'newpost_weight', 'weight'],
        [ShippingSettings::VOLUME, 'newpost_volume', 'volume'],
        // Без платника: старий модуль не мав «як у замовленні», тож його явний вибір вимкнув би безпечне типове значення.
        [self::WAYBILL . 'payment_method', 'novapost_payment_method', ['Cash', 'NonCash']],
        // Без типу вантажу: старе типове значення «Вантаж» означає понад 30 кг, і поштомати його не приймають.
        [self::WAYBILL . 'cod_payer', 'novapost_back_payer_type', ['Recipient', 'Sender']],
        [self::WAYBILL . 'sender_phone', 'novapost_sender_phone', 'phone'],
        [self::WAYBILL . 'cutoff', 'novapost_time_today_date', 'time'],
    ];

    /**
     * @param callable(string): ?string $old
     * @param callable(string): ?string $ours
     * @return array{set: array<string, string>, existed: list<string>, skipped: list<string>}
     */
    public static function values(callable $old, callable $ours): array
    {
        $result = ['set' => [], 'existed' => [], 'skipped' => []];
        foreach (self::MAP as [$target, $source, $rule]) {
            $value = $old($source);
            if ($value === null || trim($value) === '') {
                continue;
            }
            if (trim((string)$ours($target)) !== '') {
                $result['existed'][] = $target;
                continue;
            }
            $clean = self::clean(trim($value), $rule);
            if ($clean === null) {
                $result['skipped'][] = $target;
            } else {
                $result['set'][$target] = $clean;
            }
        }

        return $result;
    }

    /** @param string|list<string> $rule */
    private static function clean(string $value, string|array $rule): ?string
    {
        if (is_array($rule)) {
            return in_array($value, $rule, true) ? $value : null;
        }
        $number = is_numeric(str_replace(',', '.', $value)) ? (float)str_replace(',', '.', $value) : null;

        return match ($rule) {
            'any' => $value,
            'weight' => $number !== null && $number > 0 && $number <= 1000 ? (string)$number : null,
            'volume' => $number !== null && $number > 0 && $number <= 10 ? (string)$number : null,
            'phone' => NpPhone::normalize($value),
            'time' => preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m) ? sprintf('%02d:%s', (int)$m[1], $m[2]) : null,
            default => null,
        };
    }
}
