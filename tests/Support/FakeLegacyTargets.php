<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyTargets;

final class FakeLegacyTargets implements LegacyTargets
{
    /** @var array<int, object> id, module, cod */
    public array $payments = [];
    /** @var array<int, float> */
    public array $volumes = [];
    /** @var list<int> */
    public array $popular = [];
    /** @var array<string, string> */
    public array $settings = [];

    public function payment(int $id): ?object
    {
        return $this->payments[$id] ?? null;
    }

    public function setPaymentCod(int $id, int $flag): void
    {
        $this->payments[$id]->cod = $flag;
    }

    public function variantVolume(int $id): ?float
    {
        return $this->volumes[$id] ?? null;
    }

    public function setVariantVolume(int $id, float $volume): void
    {
        $this->volumes[$id] = $volume;
    }

    public function popularIds(): array
    {
        return $this->popular;
    }

    public function savePopular(array $ids): void
    {
        $this->popular = $ids;
    }

    public function ourSetting(string $name): ?string
    {
        return $this->settings[$name] ?? null;
    }

    public function setOurSetting(string $name, string $value): void
    {
        $this->settings[$name] = $value;
    }
}
