<?php

namespace Modules\Sviat\NovaPoshta\Products;

use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Entities\DeliveriesEntity;
use Okay\Modules\Sviat\NovaPoshta\Products\OfferShippingRates;
use PHPUnit\Framework\TestCase;

/**
 * Розмітка Offer на сторінці товару називає ціну доставки Новою Поштою у відділення чи поштомат: ту, що
 * стоїть у способі доставки в адмінці. Адресна доставка, вимкнені способи й нульова ціна її не дають.
 */
class OfferShippingRatesTest extends TestCase
{
    private const int MODULE = 75;

    /** @var array<string, mixed> */
    private array $assigned = [];

    private static function delivery(array $fields): object
    {
        return (object)($fields + [
            'id' => 1,
            'module_id' => self::MODULE,
            'enabled' => 1,
            'paid' => 1,
            'price' => '75.00',
            'free_from' => '5000.00',
            'settings' => serialize(['service_type' => 'WarehouseWarehouse']),
        ]);
    }

    public function testWarehouseMethodGivesItsAdminPrice(): void
    {
        self::assertSame(75.0, OfferShippingRates::lowest([self::delivery([])], self::MODULE));
    }

    public function testCourierMethodIsNotAWarehouseRate(): void
    {
        $courier = self::delivery(['price' => '40.00', 'settings' => serialize(['service_type' => 'WarehouseDoors'])]);

        self::assertNull(OfferShippingRates::lowest([$courier], self::MODULE));
        self::assertSame(75.0, OfferShippingRates::lowest([$courier, self::delivery(['id' => 2])], self::MODULE));
    }

    public function testDisabledMethodIsIgnored(): void
    {
        $disabled = self::delivery(['price' => '30.00', 'enabled' => 0]);

        self::assertNull(OfferShippingRates::lowest([$disabled], self::MODULE));
        self::assertSame(75.0, OfferShippingRates::lowest([$disabled, self::delivery(['id' => 2])], self::MODULE));
    }

    public function testForeignModuleMethodIsIgnored(): void
    {
        self::assertNull(OfferShippingRates::lowest([self::delivery(['module_id' => 12, 'price' => '30.00'])], self::MODULE));
        self::assertNull(OfferShippingRates::lowest([self::delivery([])], 0), 'module id unknown: nothing is ours');
    }

    public function testLowestPositivePriceAcrossWarehouseMethods(): void
    {
        $deliveries = [
            self::delivery(['id' => 1, 'price' => '120.00']),
            self::delivery(['id' => 2, 'price' => '64.50']),
            self::delivery(['id' => 3, 'price' => '75.00']),
        ];

        self::assertSame(64.5, OfferShippingRates::lowest($deliveries, self::MODULE));
    }

    public function testZeroPriceGivesNothing(): void
    {
        self::assertNull(OfferShippingRates::lowest([self::delivery(['price' => '0.00'])], self::MODULE));
    }

    /** Ядро робить спосіб із порогом 0 безкоштовним для будь-якого кошика: справжня найнижча ціна — нуль, а нуль ми не заявляємо. */
    public function testMethodFreeForEveryCartMakesRateUnclaimable(): void
    {
        $freeForAll = self::delivery(['id' => 2, 'free_from' => '0.00']);

        self::assertNull(OfferShippingRates::lowest([self::delivery([]), $freeForAll], self::MODULE));
    }

    public function testMethodWithoutServiceTypeIsWarehouse(): void
    {
        self::assertSame(75.0, OfferShippingRates::lowest([self::delivery(['settings' => ''])], self::MODULE));
    }

    public function testEntryIsAppendedNextToOtherCarriers(): void
    {
        $other = ['carrier' => 'ukrposhta', 'rate' => 55.0, 'transit_min' => 2, 'transit_max' => 4];

        self::assertSame(
            [$other, ['carrier' => 'novaposhta', 'rate' => 75.0, 'transit_min' => null, 'transit_max' => null]],
            OfferShippingRates::withEntry([$other], 75.0)
        );
    }

    public function testRepeatedCallReplacesOwnEntry(): void
    {
        $once = OfferShippingRates::withEntry(null, 75.0);

        self::assertSame([['carrier' => 'novaposhta', 'rate' => 70.0, 'transit_min' => null, 'transit_max' => null]], OfferShippingRates::withEntry($once, 70.0));
    }

    public function testAssignAddsEntryFromEnabledWarehouseMethods(): void
    {
        $this->rates([self::delivery(['price' => '60.00'])])->assign();

        self::assertSame([['carrier' => 'novaposhta', 'rate' => 60.0, 'transit_min' => null, 'transit_max' => null]], $this->assigned[OfferShippingRates::VAR]);
    }

    /** Сток без способів доставки модуля: тема лишає shippingDetails без ціни, як і була. */
    public function testAssignWithoutOwnMethodsLeavesTemplateUntouched(): void
    {
        $this->rates([])->assign();

        self::assertSame([], $this->assigned);
    }

    public function testAssignAsksOnlyForEnabledMethodsOfThisModule(): void
    {
        $filters = [];
        $this->rates([], $filters)->assign();

        self::assertSame([['enabled' => 1, 'module_id' => self::MODULE]], $filters);
    }

    /** @param list<object> $deliveries */
    private function rates(array $deliveries, ?array &$filters = null): OfferShippingRates
    {
        $filters = [];
        $entity = $this->createStub(DeliveriesEntity::class);
        $entity->method('find')->willReturnCallback(static function (array $filter) use ($deliveries, &$filters): array {
            $filters[] = $filter;

            return $deliveries;
        });
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturn($entity);

        $module = $this->createStub(Module::class);
        $module->method('getModuleIdByNamespace')->willReturn(self::MODULE);

        $design = $this->createStub(Design::class);
        $design->method('getVar')->willReturnCallback(fn(string $name) => $this->assigned[$name] ?? null);
        $design->method('assign')->willReturnCallback(function ($name, $value) use ($design) {
            $this->assigned[$name] = $value;

            return $design;
        });

        return new OfferShippingRates($factory, $module, $design);
    }
}
