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

    /**
     * Модуль ніколи не замовляє забір кур'єром: ціну для збереженого Doors* (NovaposhtaCost) рахують від відділення.
     */
    public function testPricedServiceTypeIsFromTheBranch(): void
    {
        self::assertSame('WarehouseWarehouse', DeliveryMode::fromServiceType('DoorsWarehouse')->serviceType());
        self::assertSame('WarehouseDoors', DeliveryMode::fromServiceType('DoorsDoors')->serviceType());
        self::assertSame('WarehouseDoors', DeliveryMode::Address->serviceType());
    }

    /** Модуль має два режими: забір кур'єром з магазину (Doors*) у його накладних не замовляється. */
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

    /** Форма ядра вибирає опцію лише за точним збігом: збережений Doors* скотився б до першої — пунктів видачі. */
    public function testStoredSettingsShowAnOfferedMode(): void
    {
        self::assertSame(['service_type' => 'WarehouseDoors', 'x' => 1], DeliveryMode::offered(['service_type' => 'DoorsDoors', 'x' => 1]));
        self::assertSame(['service_type' => 'WarehouseWarehouse'], DeliveryMode::offered(['service_type' => 'DoorsWarehouse']));
        self::assertSame(['service_type' => 'WarehouseDoors'], DeliveryMode::offered(['service_type' => 'WarehouseDoors']));
        self::assertSame([], DeliveryMode::offered([]), 'a new method has no mode yet');
    }
}
