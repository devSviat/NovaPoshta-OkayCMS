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

    public function testSettingsFileOffersTheSameValuesAsNovaposhtaCost(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/settings.xml');

        self::assertSame('service_type', (string)$xml->settings->variable);
        $values = [];
        foreach ($xml->settings->options as $option) {
            $values[] = (string)$option->value;
        }
        self::assertSame(['WarehouseWarehouse', 'WarehouseDoors', 'DoorsWarehouse', 'DoorsDoors'], $values);
    }
}
