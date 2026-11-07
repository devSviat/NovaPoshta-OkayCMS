<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Quote\AddressCityResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\CityRefUnavailable;

final class SettlementCityRefs implements RecipientCities
{
    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly AddressCityResolver $resolver,
    ) {
    }

    public function cityRef(int $settlementId): ?string
    {
        $settlement = $settlementId > 0 ? $this->entityFactory->get(SettlementEntity::class)->get($settlementId) : null;
        if (!$settlement) {
            return null;
        }
        try {
            return $this->resolver->forSettlement($settlement);
        } catch (CityRefUnavailable) {
            return null;
        }
    }
}
