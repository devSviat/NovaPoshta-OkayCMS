<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;

/**
 * Ref міста старого API для населеного пункту нового довідника. Місток — номер пункту видачі:
 * новий `number` = старий `WarehouseIndex` (перевірено наживо 06.11.2026), а `getWarehouses` за індексом
 * повертає `CityRef` пункту. `externalId` не використовується.
 */
final class CityRefResolver
{
    private const int KNOWN_TTL = 30 * 86400;
    private const int UNKNOWN_TTL = 86400;
    private const int NUMBERS_TRIED = 3;
    private const int TIMEOUT_SECONDS = 5;

    private readonly \Closure $now;

    public function __construct(
        private readonly OldApiClient $api,
        private readonly CityRefStore $store,
        ?\Closure $now = null,
    ) {
        $this->now = $now ?? static fn(): int => time();
    }

    /** @throws CityRefUnavailable коли Нова Пошта не відповідає, а нічого ще не відомо */
    public function forSettlement(int $settlementId): ?string
    {
        $now = ($this->now)();
        $cached = $this->store->cached($settlementId);
        if ($cached !== null && $now - $cached['checkedAt'] < ($cached['ref'] === null ? self::UNKNOWN_TTL : self::KNOWN_TTL)) {
            return $cached['ref'];
        }

        $api = $this->api->quick(self::TIMEOUT_SECONDS);
        foreach ($this->store->bridgeNumbers($settlementId, self::NUMBERS_TRIED) as $number) {
            try {
                $rows = $api->call('Address', 'getWarehouses', ['WarehouseIndex' => $number, 'Limit' => '1']);
            } catch (NpApiException $e) {
                if ($e->kind === NpErrorKind::Rejected) {
                    continue;
                }
                // Нова Пошта зараз недосяжна: нічого нового не кажемо, лишаємо те, що було відомо.
                return $cached['ref'] ?? throw new CityRefUnavailable($e->getMessage());
            }
            $row = $rows[0] ?? null;
            $ref = is_array($row) ? (string)($row['CityRef'] ?? '') : '';
            if (($row['WarehouseIndex'] ?? null) === $number && self::isRef($ref)) {
                $this->store->remember($settlementId, $ref, $now);

                return $ref;
            }
        }

        $this->store->remember($settlementId, null, $now);

        return null;
    }

    private static function isRef(string $value): bool
    {
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value);
    }
}
