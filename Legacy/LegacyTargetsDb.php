<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

use Okay\Core\EntityFactory;
use Okay\Core\Settings;
use Okay\Entities\PaymentsEntity;
use Okay\Entities\VariantsEntity;
use Okay\Modules\Sviat\NovaPoshta\Cart\PopularCities;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;
use Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume;

final class LegacyTargetsDb implements LegacyTargets
{
    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly Settings $settings,
        private readonly PopularCities $popular,
    ) {
    }

    public function payment(int $id): ?object
    {
        $payment = $this->entityFactory->get(PaymentsEntity::class)->get($id);

        return $payment ? (object)['id' => (int)$payment->id, 'name' => (string)$payment->name, 'module' => $payment->module ?? null, 'cod' => (int)($payment->{CodPayments::FIELD} ?? 0)] : null;
    }

    public function setPaymentCod(int $id, int $flag): void
    {
        $this->entityFactory->get(PaymentsEntity::class)->update($id, [CodPayments::FIELD => $flag]);
    }

    public function variantVolume(int $id): ?float
    {
        $variant = $this->entityFactory->get(VariantsEntity::class)->get($id);

        return $variant ? (float)($variant->{VariantVolume::FIELD} ?? 0) : null;
    }

    public function setVariantVolume(int $id, float $volume): void
    {
        $this->entityFactory->get(VariantsEntity::class)->update($id, [VariantVolume::FIELD => $volume]);
    }

    public function popularIds(): array
    {
        return array_map(static fn(array $city): int => $city['id'], $this->popular->list('ua'));
    }

    public function savePopular(array $ids): void
    {
        $this->popular->save($ids);
    }

    public function ourSetting(string $name): ?string
    {
        $value = $this->settings->get($name);

        return is_scalar($value) ? (string)$value : null;
    }

    public function setOurSetting(string $name, string $value): void
    {
        $this->settings->set($name, $value);
    }
}
