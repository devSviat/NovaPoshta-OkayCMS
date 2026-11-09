<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Core\EntityFactory;
use Okay\Core\Money;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Entities\VariantsEntity;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;

/** Товари замовлення як посилка: те саме, що метрики кошика для кошика, — для замовлення в адмінці. */
final class OrderParcel
{
    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly Money $money,
    ) {
    }

    /** @return list<object> покупки, кожна зі своїм `variant` (weight, volume) */
    public function purchases(int $orderId): array
    {
        $purchases = array_values($this->entityFactory->get(PurchasesEntity::class)->find(['order_id' => $orderId]));
        $variantIds = array_values(array_filter(array_map(static fn($p): int => (int)$p->variant_id, $purchases)));
        $variants = $variantIds === [] ? [] : $this->entityFactory->get(VariantsEntity::class)->mappedBy('id')->find(['id' => $variantIds]);
        foreach ($purchases as $purchase) {
            $purchase->variant = $variants[(int)$purchase->variant_id] ?? null;
        }

        return $purchases;
    }

    /** Гривень за одиницю основної валюти магазину (суми замовлення в ній); 1.0, якщо валюти UAH немає. */
    public function uahRate(): float
    {
        $uah = $this->entityFactory->get(CurrenciesEntity::class)->findOne(['code' => 'UAH']);

        return $uah ? (float)$this->money->convert(1, (int)$uah->id, false, false, 6) : 1.0;
    }

    public function metrics(object $order): CartMetrics
    {
        return self::metricsOf($this->purchases((int)$order->id), (float)($order->total_price ?? 0), $this->uahRate());
    }

    public static function metricsOf(array $purchases, float $total, float $uahRate): CartMetrics
    {
        return CartMetrics::fromPurchases($purchases, $total)->inUah(round($total * $uahRate, 2));
    }

    /** Замовлення у формі, у якій CartQuotes рахує ціну кошика: як і в кошика, його сума — товари без доставки. */
    public static function asCart(object $order, array $purchases): object
    {
        $total = (float)($order->total_price ?? 0);
        $goods = empty($order->separate_delivery) ? $total - (float)($order->delivery_price ?? 0) : $total;

        return (object)['purchases' => $purchases, 'total_price' => max(0.0, $goods)];
    }
}
