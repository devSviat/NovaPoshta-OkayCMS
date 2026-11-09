<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/** What the customer picks for a delivery method: a pickup point or an address. */
enum DeliveryMode: string
{
    case Points = 'points';
    case Address = 'address';

    /**
     * `service_type` is the NovaposhtaCost setting kept under the same name, so a switched
     * delivery method keeps it; its second half is the recipient side (…Warehouse / …Doors).
     */
    public static function fromServiceType(?string $serviceType): self
    {
        return str_ends_with((string)$serviceType, 'Doors') ? self::Address : self::Points;
    }

    /**
     * Delivery settings with `service_type` as one of the options settings.xml offers, so the
     * method's form shows the stored mode and saving keeps it.
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

    /** The service type priced and shipped: always from a branch — the module orders no courier pickup. */
    public function serviceType(): string
    {
        return $this === self::Address ? 'WarehouseDoors' : 'WarehouseWarehouse';
    }
}
