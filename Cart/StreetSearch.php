<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Quote\AddressCityResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\CityRefUnavailable;

/**
 * Street suggestions for address delivery. The new API has no street dictionary (06.11.2026);
 * the old `getStreet` by city Ref gives the street Ref an address shipment needs later.
 * A failure only means no suggestions: the customer can still type the street.
 */
final class StreetSearch
{
    private const int MIN = 2;
    private const int MAX = 50;
    private const int TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly OldApiClient $api,
        private readonly AddressCityResolver $cities,
    ) {
    }

    /** @return array{streets: list<array{ref:string, name:string}>, problem: ?string} */
    public function find(object $settlement, string $raw): array
    {
        $query = trim(preg_replace('/[\p{Cc}\p{Cf}]+/u', '', $raw) ?? '');
        $length = mb_strlen($query);
        if ($length < self::MIN || $length > self::MAX) {
            return ['streets' => [], 'problem' => null];
        }
        try {
            $cityRef = $this->cities->forSettlement($settlement);
        } catch (CityRefUnavailable) {
            return ['streets' => [], 'problem' => 'unreachable'];
        }
        if ($cityRef === null) {
            return ['streets' => [], 'problem' => 'no_city_ref'];
        }
        try {
            $rows = $this->api->quick(self::TIMEOUT_SECONDS)->call('Address', 'getStreet', ['CityRef' => $cityRef, 'FindByString' => $query, 'Limit' => '10', 'Page' => '1']);
        } catch (NpApiException $e) {
            return ['streets' => [], 'problem' => $e->kind === NpErrorKind::Rejected ? null : 'unreachable'];
        }

        $streets = [];
        foreach ($rows as $row) {
            $ref = is_array($row) ? (string)($row['Ref'] ?? '') : '';
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $ref)) {
                $streets[] = ['ref' => $ref, 'name' => trim((string)($row['StreetsType'] ?? '') . ' ' . (string)($row['Description'] ?? ''))];
            }
        }

        return ['streets' => $streets, 'problem' => null];
    }
}
