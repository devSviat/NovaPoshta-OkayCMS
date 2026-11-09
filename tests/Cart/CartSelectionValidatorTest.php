<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartSelection;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartSelectionValidator;
use PHPUnit\Framework\TestCase;

class CartSelectionValidatorTest extends TestCase
{
    private const int MODULE = 74;

    private static function delivery(array $override = []): object
    {
        return (object)($override + ['id' => 4, 'module_id' => self::MODULE, 'settings' => ['service_type' => 'WarehouseWarehouse']]);
    }

    private static function groupRows(): array
    {
        return [
            (object)['id' => 1, 'name' => 'Відділення', 'enabled' => 1, 'position' => 1, 'categories' => '{"PostBranch":true,"PUDO":false}'],
            (object)['id' => 2, 'name' => 'Поштомат', 'enabled' => 0, 'position' => 2, 'categories' => '{"Postomat":true}'],
        ];
    }

    private static function settlement(): object
    {
        return (object)['id' => 118064, 'name_uk' => 'місто Київ', 'district_uk' => null, 'area_uk' => 'Київська область'];
    }

    private static function division(array $override = []): object
    {
        return (object)($override + [
            'id' => 7162, 'settlement_id' => 118064, 'number' => '11/1', 'category' => 'PostBranch', 'status' => 'Working', 'prohibited_issuance' => 0,
            'name_uk' => 'Відділення №1: вул. Пирогівський шлях, 135', 'address_uk' => 'Київ, Пирогівський шлях, 135', 'max_weight' => '30.00', 'max_declared_cost' => '100000.00',
        ]);
    }

    private static function check(?object $delivery, ?object $settlement, ?object $division, ?CartMetrics $cart = null, bool $dictionaryReady = true): CartSelection|string
    {
        return CartSelectionValidator::check($delivery, self::MODULE, $settlement, $division, self::groupRows(), $cart ?? new CartMetrics(2, false, 1000), $dictionaryReady);
    }

    public function testValidChoice(): void
    {
        $selection = self::check(self::delivery(), self::settlement(), self::division());

        self::assertInstanceOf(CartSelection::class, $selection);
        self::assertTrue($selection->applies());
        self::assertSame(7162, $selection->division->id);
    }

    public function testForeignDeliveryIsIgnored(): void
    {
        $selection = self::check(self::delivery(['module_id' => 12]), null, null);

        self::assertFalse($selection->applies());
    }

    public function testNoDeliveryIsIgnored(): void
    {
        self::assertFalse(self::check(null, null, null)->applies());
    }

    public function testAddressModeIsNotHandledYet(): void
    {
        self::assertFalse(self::check(self::delivery(['settings' => ['service_type' => 'WarehouseDoors']]), null, null)->applies());
    }

    public function testMissingSettlement(): void
    {
        self::assertSame('error_city', self::check(self::delivery(), null, null));
    }

    public function testEmptyDictionaryLetsTheOrderThroughWithoutAPoint(): void
    {
        // Тоді кошик просить покупця вказати відділення в коментарі.
        self::assertFalse(self::check(self::delivery(), null, null, null, false)->applies());
    }

    public function testMissingPoint(): void
    {
        self::assertSame('error_point', self::check(self::delivery(), self::settlement(), null));
    }

    public function testPointOfAnotherSettlement(): void
    {
        self::assertSame('error_point', self::check(self::delivery(), self::settlement(), self::division(['settlement_id' => 121300])));
    }

    public function testPointNotWorking(): void
    {
        self::assertSame('error_point_unavailable', self::check(self::delivery(), self::settlement(), self::division(['status' => 'NotWorkingTemporary'])));
        self::assertSame('error_point_unavailable', self::check(self::delivery(), self::settlement(), self::division(['prohibited_issuance' => 1])));
        self::assertSame('error_point_unavailable', self::check(self::delivery(), self::settlement(), self::division(['name_uk' => 'Поштомат "Нова Пошта" №7400: ТЕСТОВИЙ'])));
    }

    public function testPointOfHiddenCategoryOrDisabledGroup(): void
    {
        self::assertSame('error_point_unavailable', self::check(self::delivery(), self::settlement(), self::division(['category' => 'PUDO'])));
        self::assertSame('error_point_unavailable', self::check(self::delivery(), self::settlement(), self::division(['category' => 'Postomat'])));
    }

    public function testPointThatDoesNotFit(): void
    {
        self::assertSame('error_point_too_heavy', self::check(self::delivery(), self::settlement(), self::division(), new CartMetrics(31, false, 1000)));
        self::assertSame('error_point_too_expensive', self::check(self::delivery(), self::settlement(), self::division(['max_declared_cost' => '500.00'])));
    }

    public function testPostedIdsAreReadForTheChosenDeliveryOnly(): void
    {
        $post = ['novaposhta' => ['4' => ['settlement_id' => '118064', 'division_id' => '7162'], '9' => ['settlement_id' => '1', 'division_id' => '2']]];

        self::assertSame([118064, 7162], CartSelectionValidator::postedIds($post, 4));
        self::assertSame([0, 0], CartSelectionValidator::postedIds($post, 5));
        self::assertSame([0, 0], CartSelectionValidator::postedIds(['novaposhta' => 'x'], 4));
    }

    public function testRestoredPointThatStillFitsIsKept(): void
    {
        $kept = CartSelectionValidator::keepRestored(self::delivery(), self::MODULE, self::settlement(), self::division(), self::groupRows(), new CartMetrics(2, false, 1000));

        self::assertSame(7162, $kept?->id);
    }

    public function testRestoredPointThatNoLongerFitsIsDropped(): void
    {
        $tooHeavy = CartSelectionValidator::keepRestored(self::delivery(), self::MODULE, self::settlement(), self::division(), self::groupRows(), new CartMetrics(40, false, 1000));
        $closed = CartSelectionValidator::keepRestored(self::delivery(), self::MODULE, self::settlement(), self::division(['status' => 'Closed']), self::groupRows(), new CartMetrics(2, false, 1000));

        self::assertNull($tooHeavy);
        self::assertNull($closed);
    }
}
