<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows;

/** Запис населеного пункту з CDN як рядок таблиці. `boost` тут немає: він приходить з API і переживає перезавантаження. */
final class SettlementRow
{
    public static function fromNp(array $uk, ?array $en): array
    {
        [$districtUk, $areaUk] = self::regions($uk['region'] ?? null);
        [$districtEn, $areaEn] = self::regions($en['region'] ?? null);

        return RowHash::with([
            'id' => (int)$uk['id'],
            'name_uk' => Fit::text($uk['name'] ?? '', 255) ?? '',
            'name_en' => Fit::text($en['name'] ?? null, 255),
            'district_uk' => Fit::text($districtUk, 255),
            'district_en' => Fit::text($districtEn, 255),
            'area_uk' => Fit::text($areaUk, 255),
            'area_en' => Fit::text($areaEn, 255),
            'post_code' => Fit::text($uk['postCode1'] ?? null, 10),
            'search_text' => NameNormalizer::searchText(
                (string)($uk['name'] ?? ''),
                [...(array)($uk['alternativeNames'] ?? []), ...(isset($en['name']) ? [(string)$en['name']] : [])],
            ),
            'prohibited_issuance' => empty($uk['prohibitedIssuance']) ? 0 : 1,
        ]);
    }

    /**
     * `region` — це район, а `region.parent` — область; місто обласного значення
     * не має parent, і його `region` — це сама область.
     *
     * @return array{0: ?string, 1: ?string} район, область
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
