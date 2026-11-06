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
            'number' => (string)($uk['number'] ?? ''),
            'category' => (string)($uk['divisionCategory'] ?? ''),
            'status' => (string)($uk['status'] ?? ''),
            'name_uk' => (string)($uk['name'] ?? ''),
            'name_en' => self::text($en['name'] ?? null),
            'short_name_uk' => self::text($uk['shortName'] ?? null),
            'short_name_en' => self::text($en['shortName'] ?? null),
            'address_uk' => self::text($uk['address'] ?? null),
            'address_en' => self::text($en['address'] ?? null),
            'post_code' => self::text($uk['addressParts']['postCode'] ?? null),
            'latitude' => self::number($uk['latitude'] ?? null),
            'longitude' => self::number($uk['longitude'] ?? null),
            'max_weight' => self::number($uk['maxWeightPlaceRecipient'] ?? null),
            'max_length' => self::integer($uk['maxLengthPlaceRecipient'] ?? null),
            'max_width' => self::integer($uk['maxWidthPlaceRecipient'] ?? null),
            'max_height' => self::integer($uk['maxHeightPlaceRecipient'] ?? null),
            'max_declared_cost' => self::number($uk['maxDeclaredCostPlace'] ?? null),
            'prohibited_issuance' => empty($uk['prohibitedIssuance']) ? 0 : 1,
            'schedule' => isset($uk['workSchedule']) ? json_encode($uk['workSchedule'], JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float)$value : null;
    }

    private static function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int)$value : null;
    }
}
