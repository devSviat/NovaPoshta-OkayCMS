<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyData;

/** Old modules' data held in arrays; rows are objects with the columns LegacyTables returns. */
final class FakeLegacyData implements LegacyData
{
    /** @var list<object> */
    public array $delivery = [];
    /** @var list<object> */
    public array $tracking = [];
    /** @var array<string, object> */
    public array $warehouses = [];
    /** @var array<string, string> */
    public array $cities = [];
    /** @var list<int> */
    public array $cod = [];
    /** @var list<object> */
    public array $variantVolumes = [];
    /** @var list<object> */
    public array $popular = [];
    /** @var array<string, string> */
    public array $settings = [];
    /** @var list<string> */
    public array $enabled = [];
    /** @var list<object> */
    public array $deliveries = [];

    public function deliveryRows(int $afterId, int $limit): array
    {
        return self::page($this->delivery, $afterId, $limit);
    }

    public function trackingRows(int $afterId, int $limit): array
    {
        return self::page($this->tracking, $afterId, $limit);
    }

    public function warehouse(string $ref): ?object
    {
        return $this->warehouses[$ref] ?? null;
    }

    public function cityName(string $cityRef): ?string
    {
        return $this->cities[$cityRef] ?? null;
    }

    public function codPaymentIds(): array
    {
        return $this->cod;
    }

    public function volumes(int $afterId, int $limit): array
    {
        return self::page($this->variantVolumes, $afterId, $limit);
    }

    public function popularCities(): array
    {
        return $this->popular;
    }

    public function setting(string $name): ?string
    {
        return $this->settings[$name] ?? null;
    }

    public function counts(): array
    {
        return ['orders' => count($this->delivery), 'waybills' => count($this->tracking), 'payments' => count($this->cod), 'volumes' => count($this->variantVolumes), 'popular' => count($this->popular)];
    }

    public function enabledModules(): array
    {
        return $this->enabled;
    }

    public function legacyDeliveries(): array
    {
        return $this->deliveries;
    }

    /** @param list<object> $rows */
    private static function page(array $rows, int $afterId, int $limit): array
    {
        return array_slice(array_values(array_filter($rows, static fn(object $r): bool => (int)$r->id > $afterId)), 0, $limit);
    }
}
