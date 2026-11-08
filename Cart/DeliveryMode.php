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

    /** The service type priced and shipped: always from a branch — the module orders no courier pickup. */
    public function serviceType(): string
    {
        return $this === self::Address ? 'WarehouseDoors' : 'WarehouseWarehouse';
    }
}
