<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

use Okay\Core\EntityFactory;
use Okay\Core\Money;
use Okay\Entities\CurrenciesEntity;

/** Метрики кошика із сумою в гривнях — валюті лімітів Нової Пошти. */
final class UahTotal
{
    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly Money $money,
    ) {
    }

    public function metrics(object $cart): CartMetrics
    {
        $metrics = CartMetrics::fromCart($cart);
        $uah = $this->entityFactory->get(CurrenciesEntity::class)->findOne(['code' => 'UAH']);

        return $uah ? $metrics->inUah((float)$this->money->convert($metrics->total, (int)$uah->id, false, false, 2)) : $metrics;
    }
}
