<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;

/**
 * The old API's city Ref for a settlement of the new dictionary. The bridge is the point number:
 * new `number` = old `WarehouseIndex` (checked live 06.11.2026), and `getWarehouses` by index
 * returns the point's `CityRef`. `externalId` is not used.
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

    /** @throws CityRefUnavailable when Nova Poshta does not answer and nothing is known yet */
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
                // Nova Poshta is not reachable now: say nothing new, keep what was known.
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
