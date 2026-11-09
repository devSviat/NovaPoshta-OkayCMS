<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/** Що покупець обирає для способу доставки: пункт видачі чи адресу. */
enum DeliveryMode: string
{
    case Points = 'points';
    case Address = 'address';

    /**
     * `service_type` — налаштування NovaposhtaCost, збережене під тією самою назвою, тож переключений
     * спосіб доставки його зберігає; друга половина — сторона отримувача (…Warehouse / …Doors).
     */
    public static function fromServiceType(?string $serviceType): self
    {
        return str_ends_with((string)$serviceType, 'Doors') ? self::Address : self::Points;
    }

    /**
     * Налаштування доставки з `service_type`, що є одним із варіантів, які пропонує settings.xml, тож
     * форма способу показує збережений режим, а збереження його не втрачає.
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function offered(array $settings): array
    {
        if (isset($settings['service_type'])) {
            $settings['service_type'] = self::fromServiceType((string)$settings['service_type'])->serviceType();
        }

        return $settings;
    }

    /** Тип послуги, за яким рахують ціну й відправляють: завжди з відділення — модуль не замовляє забір кур'єром. */
    public function serviceType(): string
    {
        return $this === self::Address ? 'WarehouseDoors' : 'WarehouseWarehouse';
    }
}
