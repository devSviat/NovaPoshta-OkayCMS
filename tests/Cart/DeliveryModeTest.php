<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeliveryModeTest extends TestCase
{
    public static function serviceTypes(): array
    {
        return [
            ['WarehouseWarehouse', DeliveryMode::Points],
            ['DoorsWarehouse', DeliveryMode::Points],
            ['WarehouseDoors', DeliveryMode::Address],
            ['DoorsDoors', DeliveryMode::Address],
            [null, DeliveryMode::Points],
            ['', DeliveryMode::Points],
            ['nonsense', DeliveryMode::Points],
        ];
    }

    #[DataProvider('serviceTypes')]
    public function testModeFollowsTheRecipientSide(?string $serviceType, DeliveryMode $mode): void
    {
        self::assertSame($mode, DeliveryMode::fromServiceType($serviceType));
    }

    /** The module never orders a courier pickup: a stored Doors* (NovaposhtaCost) is priced from the branch. */
    public function testPricedServiceTypeIsFromTheBranch(): void
    {
        self::assertSame('WarehouseWarehouse', DeliveryMode::fromServiceType('DoorsWarehouse')->serviceType());
        self::assertSame('WarehouseDoors', DeliveryMode::fromServiceType('DoorsDoors')->serviceType());
        self::assertSame('WarehouseDoors', DeliveryMode::Address->serviceType());
    }

    /** Two modes the module ships: courier pickup from the shop (Doors*) is not ordered on its waybills. */
    public function testSettingsFileOffersTheTwoModes(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/settings.xml');

        self::assertSame('service_type', (string)$xml->settings->variable);
        $values = [];
        foreach ($xml->settings->options as $option) {
            $values[] = (string)$option->value;
        }
        self::assertSame(['WarehouseWarehouse', 'WarehouseDoors'], $values);
    }
}
