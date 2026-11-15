<?php

namespace Modules\Sviat\NovaPoshta\Extenders;

use Okay\Core\Cart;
use Okay\Core\EntityFactory;
use Okay\Core\FrontTranslations;
use Okay\Core\Modules\Module;
use Okay\Core\Money;
use Okay\Core\Settings;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\DeliveriesEntity;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartSelectionValidator;
use Okay\Modules\Sviat\NovaPoshta\Cart\SettlementSearch;
use Okay\Modules\Sviat\NovaPoshta\Cart\UahTotal;
use Okay\Modules\Sviat\NovaPoshta\Entities\GroupEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Extenders\FrontExtender;
use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsStore;
use PHPUnit\Framework\TestCase;

/**
 * Ланка ValidateHelper::getCartValidateError. Ланка контактів раніше в ланцюжку може зняти свою помилку
 * як null чи як '' (обидва дозволяє контракт ядра), і в обох випадках вибір пункту має перевірятися.
 */
class CartValidateChainTest extends TestCase
{
    private const int MODULE = 5;
    private const int OWN_DELIVERY = 3;

    public function testClearedAsEmptyStringStillValidatesThePoint(): void
    {
        self::assertSame('sviat__novaposhta__error_city', $this->front()->getCartValidateError('', $this->order()));
    }

    public function testControlNullValidatesThePoint(): void
    {
        self::assertSame('sviat__novaposhta__error_city', $this->front()->getCartValidateError(null, $this->order()));
    }

    public function testControlEarlierErrorIsKept(): void
    {
        self::assertSame('empty_phone', $this->front()->getCartValidateError('empty_phone', $this->order()));
    }

    private function order(): object
    {
        return (object)['id' => null, 'delivery_id' => self::OWN_DELIVERY, 'payment_method_id' => 0];
    }

    private static function blank(string $class): object
    {
        return new \ReflectionClass($class)->newInstanceWithoutConstructor();
    }

    private function front(): FrontExtender
    {
        $deliveries = $this->createStub(DeliveriesEntity::class);
        $deliveries->method('get')->willReturn((object)['id' => self::OWN_DELIVERY, 'module_id' => self::MODULE, 'settings' => serialize(['service_type' => 'WarehouseWarehouse'])]);
        $settlements = $this->createStub(SettlementEntity::class);
        $settlements->method('get')->willReturn(false);
        $groups = $this->createStub(GroupEntity::class);
        $groups->method('find')->willReturn([]);
        $currencies = $this->createStub(CurrenciesEntity::class);
        $currencies->method('findOne')->willReturn(false);
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(static fn(string $class) => match ($class) {
            DeliveriesEntity::class => $deliveries,
            SettlementEntity::class => $settlements,
            GroupEntity::class => $groups,
            CurrenciesEntity::class => $currencies,
        });
        $module = $this->createStub(Module::class);
        $module->method('getModuleIdByNamespace')->willReturn(self::MODULE);
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturn(null);
        $translations = $this->createStub(FrontTranslations::class);
        $translations->method('getTranslation')->willReturnArgument(0);

        $search = self::blank(SettlementSearch::class);
        new \ReflectionProperty(SettlementSearch::class, 'hasAny')->setValue($search, true);
        $validator = new CartSelectionValidator($factory, new GroupsStore($factory), $module, $search, new ShippingSettings($settings, $factory));

        $front = self::blank(FrontExtender::class);
        foreach ([
            'module' => $module,
            'translations' => $translations,
            'validator' => $validator,
            'cart' => $this->createStub(Cart::class),
            'uahTotal' => new UahTotal($factory, $this->createStub(Money::class)),
        ] as $property => $value) {
            new \ReflectionProperty(FrontExtender::class, $property)->setValue($front, $value);
        }

        return $front;
    }
}
