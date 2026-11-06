<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows;

/** A CDN settlement record as a table row. `boost` is not here: it comes from the API and survives reloads. */
final class SettlementRow
{
    public static function fromNp(array $uk, ?array $en): array
    {
        [$districtUk, $areaUk] = self::regions($uk['region'] ?? null);
        [$districtEn, $areaEn] = self::regions($en['region'] ?? null);

        return RowHash::with([
            'id' => (int)$uk['id'],
            'name_uk' => (string)($uk['name'] ?? ''),
            'name_en' => is_string($en['name'] ?? null) ? $en['name'] : null,
            'district_uk' => $districtUk,
            'district_en' => $districtEn,
            'area_uk' => $areaUk,
            'area_en' => $areaEn,
            'post_code' => is_string($uk['postCode1'] ?? null) ? $uk['postCode1'] : null,
            'search_text' => NameNormalizer::searchText(
                (string)($uk['name'] ?? ''),
                [...(array)($uk['alternativeNames'] ?? []), ...(isset($en['name']) ? [(string)$en['name']] : [])],
            ),
            'prohibited_issuance' => empty($uk['prohibitedIssuance']) ? 0 : 1,
        ]);
    }

    /**
     * `region` is the district and `region.parent` the oblast; a city of regional significance
     * has no parent and its `region` is the oblast itself.
     *
     * @return array{0: ?string, 1: ?string} district, oblast
     */
    private static function regions(mixed $region): array
    {
        if (!is_array($region)) {
            return [null, null];
        }
        $parent = $region['parent'] ?? null;
        if (is_array($parent)) {
            return [$region['name'] ?? null, $parent['name'] ?? null];
        }

        return [null, $region['name'] ?? null];
    }
}
