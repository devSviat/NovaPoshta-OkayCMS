<?php

namespace Okay\Modules\Sviat\NovaPoshta\Products;

use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Entities\DeliveriesEntity;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Quote\FreeDelivery;

/**
 * Ціна доставки для розмітки Offer на сторінці товару (schema.org shippingDetails → shippingRate).
 *
 * Тема читає `$offer_shipping_rates`: список записів `{carrier, rate, transit_min, transit_max}`, по одному
 * на перевізника, і виводить окремий OfferShippingDetails на кожен. `rate` — в основній валюті магазину,
 * transit_* у днях або null (тоді тема бере свій типовий термін). Модуль іншого перевізника додає свій
 * запис так само: читає змінну, дописує себе, присвоює назад; чужі записи не чіпає.
 */
final class OfferShippingRates
{
    public const string VAR = 'offer_shipping_rates';
    public const string CARRIER = 'novaposhta';

    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly Module $module,
        private readonly Design $design,
    ) {
    }

    public function assign(): void
    {
        $moduleId = (int)$this->module->getModuleIdByNamespace(__CLASS__);
        if ($moduleId <= 0) {
            return;
        }
        $deliveries = $this->entityFactory->get(DeliveriesEntity::class)->find(['enabled' => 1, 'module_id' => $moduleId]);
        $rate = self::lowest(is_array($deliveries) ? $deliveries : [], $moduleId);
        if ($rate !== null) {
            $this->design->assign(self::VAR, self::withEntry($this->design->getVar(self::VAR), $rate));
        }
    }

    /**
     * Найнижча ціна з адмінки серед увімкнених способів модуля з доставкою у відділення чи поштомат — та сама,
     * що кошик показує, поки Нова Пошта не порахувала свою. Адресна доставка має власну ціну й сюди не входить.
     * Спосіб, який ядро робить безкоштовним для будь-якого кошика (поріг 0), важить нуль: тоді чесна найнижча
     * ціна — безкоштовно, а безкоштовну доставку розмітка не обіцяє, тож результат null.
     *
     * @param iterable<object> $deliveries
     */
    public static function lowest(iterable $deliveries, int $moduleId): ?float
    {
        $lowest = null;
        foreach ($deliveries as $delivery) {
            if ($moduleId <= 0 || (int)($delivery->module_id ?? 0) !== $moduleId || empty($delivery->enabled)
                || DeliveryMode::of($delivery) !== DeliveryMode::Points) {
                continue;
            }
            $price = FreeDelivery::applies($delivery, 0.0) ? 0.0 : (float)($delivery->price ?? 0);
            $lowest = $lowest === null ? $price : min($lowest, $price);
        }

        return $lowest !== null && $lowest > 0 ? round($lowest, 2) : null;
    }

    /**
     * Список для теми з записом цього модуля замість попереднього; записи інших перевізників лишаються.
     *
     * @return list<array{carrier: string, rate: float, transit_min: ?int, transit_max: ?int}>
     */
    public static function withEntry(mixed $current, float $rate): array
    {
        $others = array_filter(
            is_array($current) ? $current : [],
            static fn($entry): bool => is_array($entry) && ($entry['carrier'] ?? null) !== self::CARRIER
        );
        $others[] = ['carrier' => self::CARRIER, 'rate' => $rate, 'transit_min' => null, 'transit_max' => null];

        return array_values($others);
    }
}
