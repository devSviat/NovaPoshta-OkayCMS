<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;

/**
 * Ref міста старого API для адресної доставки. Населений пункт із пунктами видачі йде через місток
 * за номером пункту; без пунктів — шукається за назвою, областю й районом у `searchSettlements` (район
 * розрізняє тезок; порожній `DeliveryCity` означає, що Нова Пошта не доставляє туди за адресою —
 * перевірено наживо 06.11.2026). Усе неоднозначне лишається менеджеру, ніколи не вгадується.
 */
final class AddressCityResolver
{
    private const int KNOWN_TTL = 30 * 86400;
    private const int UNKNOWN_TTL = 86400;
    private const int TIMEOUT_SECONDS = 5;
    private const int PAGE_SIZE = 50;
    private const int MAX_PAGES = 4;
    private const string TYPES = '/^(місто|село|селище міського типу|селище|смт|хутір)\s+/u';

    private readonly \Closure $now;

    public function __construct(
        private readonly OldApiClient $api,
        private readonly CityRefStore $store,
        private readonly CityRefResolver $bridge,
        ?\Closure $now = null,
    ) {
        $this->now = $now ?? static fn(): int => time();
    }

    /**
     * @param object $settlement рядок з id, name_uk, district_uk, area_uk
     * @throws CityRefUnavailable коли Нова Пошта не відповідає, а нічого ще не відомо
     */
    public function forSettlement(object $settlement): ?string
    {
        $id = (int)$settlement->id;
        $now = ($this->now)();
        $cached = $this->store->cached($id);
        if ($cached !== null && $now - $cached['checkedAt'] < ($cached['ref'] === null ? self::UNKNOWN_TTL : self::KNOWN_TTL)) {
            return $cached['ref'];
        }
        if ($this->store->bridgeNumbers($id, 1) !== []) {
            $ref = $this->bridge->forSettlement($id);
            if ($ref !== null) {
                return $ref;
            }
        }

        $name = self::plainName((string)$settlement->name_uk);
        $rows = [];
        $complete = false;
        // Тезка в тому самому районі може бути на наступній сторінці: унікальність оцінюється за всією відповіддю.
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            try {
                $data = $this->api->quick(self::TIMEOUT_SECONDS)->call('Address', 'searchSettlements', ['CityName' => $name, 'Limit' => (string)self::PAGE_SIZE, 'Page' => (string)$page]);
            } catch (NpApiException $e) {
                if ($e->kind !== NpErrorKind::Rejected) {
                    return $cached['ref'] ?? throw new CityRefUnavailable($e->getMessage());
                }
                $data = [];
            }
            $found = array_values(array_filter((array)($data[0]['Addresses'] ?? []), 'is_array'));
            $rows = [...$rows, ...$found];
            if ($found === [] || count($rows) >= (int)($data[0]['TotalCount'] ?? 0)) {
                $complete = true;
                break;
            }
        }

        $area = self::strip((string)$settlement->area_uk, ' область');
        $district = self::strip((string)($settlement->district_uk ?? ''), ' район');
        $matches = array_values(array_filter(
            $rows,
            static fn(array $row): bool => self::same((string)($row['MainDescription'] ?? ''), $name)
                && self::same((string)($row['Area'] ?? ''), $area)
                && self::same((string)($row['Region'] ?? ''), $district),
        ));
        $ref = $complete && count($matches) === 1 ? (string)($matches[0]['DeliveryCity'] ?? '') : '';
        $ref = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $ref) ? $ref : null;
        $this->store->remember($id, $ref, $now);

        return $ref;
    }

    /** "село Мар'янівка (Колонщинська сільська рада)" → "Мар'янівка" */
    public static function plainName(string $name): string
    {
        $name = preg_replace(self::TYPES, '', trim($name)) ?? $name;

        return trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $name) ?? $name);
    }

    private static function strip(string $value, string $suffix): string
    {
        $value = trim($value);

        return str_ends_with($value, $suffix) ? trim(substr($value, 0, -strlen($suffix))) : $value;
    }

    private static function same(string $a, string $b): bool
    {
        $normal = static fn(string $s): string => mb_strtolower(str_replace(['’', 'ʼ', '`'], "'", trim($s)));

        return $normal($a) === $normal($b);
    }
}
