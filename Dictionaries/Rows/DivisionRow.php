<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows;

/**
 * A CDN division record as a table row. Takes only what the shop shows or filters by:
 * the record also carries the responsible person's name and internal phones, which stay out.
 */
final class DivisionRow
{
    /** @param array|null $en the same record from the English dictionary, when the site has English */
    public static function fromNp(array $uk, ?array $en): array
    {
        return RowHash::with([
            'id' => (int)$uk['id'],
            'settlement_id' => (int)($uk['settlement']['id'] ?? 0),
            'number' => Fit::text($uk['number'] ?? '', 32) ?? '',
            'category' => Fit::text($uk['divisionCategory'] ?? '', 32) ?? '',
            'status' => Fit::text($uk['status'] ?? '', 32) ?? '',
            'name_uk' => Fit::text($uk['name'] ?? '', 255) ?? '',
            'name_en' => Fit::text($en['name'] ?? null, 255),
            'short_name_uk' => Fit::text($uk['shortName'] ?? null, 255),
            'short_name_en' => Fit::text($en['shortName'] ?? null, 255),
            'address_uk' => Fit::text($uk['address'] ?? null, 255),
            'address_en' => Fit::text($en['address'] ?? null, 255),
            'post_code' => Fit::text($uk['addressParts']['postCode'] ?? null, 10),
            'latitude' => Fit::decimal($uk['latitude'] ?? null, 999.9999999),
            'longitude' => Fit::decimal($uk['longitude'] ?? null, 999.9999999),
            'max_weight' => Fit::decimal($uk['maxWeightPlaceRecipient'] ?? null, 99999999.99),
            'max_length' => Fit::integer($uk['maxLengthPlaceRecipient'] ?? null),
            'max_width' => Fit::integer($uk['maxWidthPlaceRecipient'] ?? null),
            'max_height' => Fit::integer($uk['maxHeightPlaceRecipient'] ?? null),
            'max_declared_cost' => Fit::decimal($uk['maxDeclaredCostPlace'] ?? null, 999999999999.99),
            'prohibited_issuance' => empty($uk['prohibitedIssuance']) ? 0 : 1,
            'schedule' => isset($uk['workSchedule']) ? json_encode($uk['workSchedule'], JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}
