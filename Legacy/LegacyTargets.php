<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Where the small pieces of old data go: payment and variant fields, popular cities, settings. */
interface LegacyTargets
{
    /** @return object|null id, module, cod (our CodPayments flag) */
    public function payment(int $id): ?object;

    public function setPaymentCod(int $id, int $flag): void;

    /** @return float|null our volume of the variant; null when the variant is gone */
    public function variantVolume(int $id): ?float;

    public function setVariantVolume(int $id, float $volume): void;

    /** @return list<int> */
    public function popularIds(): array;

    /** @param list<int> $ids */
    public function savePopular(array $ids): void;

    public function ourSetting(string $name): ?string;

    public function setOurSetting(string $name, string $value): void;
}
