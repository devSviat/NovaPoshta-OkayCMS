<?php

namespace Modules\Sviat\NovaPoshta\Extenders;

require_once __DIR__ . '/../Support/InMemoryMutex.php';
require_once __DIR__ . '/../Support/InMemoryState.php';

use Modules\Sviat\NovaPoshta\Support\InMemoryMutex;
use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Core\BackendTranslations;
use Okay\Core\Cart;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\FrontTranslations;
use Okay\Core\Languages;
use Okay\Core\Modules\Module;
use Okay\Core\Money;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartSelectionValidator;
use Okay\Modules\Sviat\NovaPoshta\Cart\PopularCities;
use Okay\Modules\Sviat\NovaPoshta\Cart\SettlementSearch;
use Okay\Modules\Sviat\NovaPoshta\Cart\UahTotal;
use Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity;
use Okay\Modules\Sviat\NovaPoshta\Extenders\BackendExtender;
use Okay\Modules\Sviat\NovaPoshta\Extenders\FrontExtender;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryLines;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryStore;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderRowsCleanup;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrdersMassAction;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;
use Okay\Modules\Sviat\NovaPoshta\Quote\AddressCityResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\CartQuotes;
use Okay\Modules\Sviat\NovaPoshta\Quote\ClientAddress;
use Okay\Modules\Sviat\NovaPoshta\Quote\QuoteService;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillDrafts;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStore;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillView;
use PHPUnit\Framework\TestCase;

/**
 * Менеджер змінив доставку замовлення на спосіб іншого модуля: рядок НП лишається в базі (повернення
 * доставки поверне й пункт), але ні сторінка замовлення, ні листи, ні адмінка старого пункту не показують.
 */
class SwitchedDeliveryTest extends TestCase
{
    private const int MODULE = 5;
    private const int OWN_DELIVERY = 3;
    private const int FOREIGN_DELIVERY = 9;
    private const int COD_PAYMENT = 4;

    /** @var array<string, mixed> */
    private array $assigned = [];
    private object $order;
    private ?object $waybill = null;

    private static function snapshot(): object
    {
        return (object)['id' => 1, 'order_id' => 77, 'mode' => 'points', 'settlement_id' => 501, 'settlement_name' => 'село Лісове', 'area_name' => 'Тестова', 'district_name' => '',
            'division_id' => 9001, 'division_name' => 'Відділення №1: вул. Центральна, 3', 'division_address' => 'вул. Центральна, 3', 'price_source' => 'new', 'cod_fee' => 21.5, 'delivery_term' => null];
    }

    private function order(int $deliveryId): void
    {
        $this->order = (object)['id' => 77, 'delivery_id' => $deliveryId, 'payment_method_id' => self::COD_PAYMENT, 'delivery_price' => 60.0, 'total_price' => 1060.0, 'separate_delivery' => 0, 'paid' => 0];
    }

    private function entityFactory(): EntityFactory
    {
        $deliveries = $this->createStub(DeliveriesEntity::class);
        $deliveries->method('get')->willReturnCallback(static fn($id) => match ((int)$id) {
            self::OWN_DELIVERY => (object)['id' => self::OWN_DELIVERY, 'module_id' => self::MODULE, 'hide_front_delivery_price' => 0, 'free_from' => 0, 'settings' => serialize(['service_type' => 'WarehouseWarehouse'])],
            self::FOREIGN_DELIVERY => (object)['id' => self::FOREIGN_DELIVERY, 'module_id' => 12, 'hide_front_delivery_price' => 0, 'free_from' => 0, 'settings' => ''],
            default => false,
        });
        $snapshots = $this->createStub(OrderDeliveryEntity::class);
        $snapshots->method('findOne')->willReturn(self::snapshot());
        $waybills = $this->createStub(WaybillEntity::class);
        $waybills->method('findOne')->willReturnCallback(fn() => $this->waybill ?? false);
        $payments = $this->createStub(PaymentsEntity::class);
        $payments->method('get')->willReturn((object)['id' => self::COD_PAYMENT, 'module' => 'null', CodPayments::FIELD => 2]);
        $orders = $this->createStub(OrdersEntity::class);
        $orders->method('get')->willReturnCallback(fn() => $this->order);
        $currencies = $this->createStub(CurrenciesEntity::class);
        $currencies->method('getMainCurrency')->willReturn((object)['sign' => 'грн']);

        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(static fn(string $class) => match ($class) {
            DeliveriesEntity::class => $deliveries,
            OrderDeliveryEntity::class => $snapshots,
            WaybillEntity::class => $waybills,
            PaymentsEntity::class => $payments,
            OrdersEntity::class => $orders,
            CurrenciesEntity::class => $currencies,
        });

        return $factory;
    }

    private function module(): Module
    {
        $module = $this->createStub(Module::class);
        $module->method('getModuleIdByNamespace')->willReturn(self::MODULE);

        return $module;
    }

    private function design(): Design
    {
        $design = $this->createStub(Design::class);
        $design->method('assign')->willReturnCallback(function ($name, $value) use ($design) {
            $this->assigned[$name] = $value;

            return $design;
        });

        return $design;
    }

    private static function blank(string $class): object
    {
        return new \ReflectionClass($class)->newInstanceWithoutConstructor();
    }

    private function front(): FrontExtender
    {
        $factory = $this->entityFactory();
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturn('');
        $quotes = new CartQuotes(
            self::blank(QuoteService::class),
            self::blank(ShippingSettings::class),
            $factory,
            new CodPayments($factory),
            $this->module(),
            $this->createStub(Money::class),
            new InMemoryState(),
            self::blank(AddressCityResolver::class),
            new InMemoryMutex(),
            $this->createStub(ClientAddress::class),
        );

        return new FrontExtender(
            $this->design(),
            $settings,
            $this->module(),
            self::blank(Request::class),
            self::blank(PopularCities::class),
            self::blank(Languages::class),
            $factory,
            $this->createStub(FrontTranslations::class),
            self::blank(CartSelectionValidator::class),
            new OrderDeliveryStore($factory),
            $this->createStub(Cart::class),
            self::blank(SettlementSearch::class),
            $quotes,
            $this->createStub(Money::class),
            self::blank(UahTotal::class),
            new WaybillStore($factory),
            $this->lines($factory),
        );
    }

    private function lines(EntityFactory $factory): OrderDeliveryLines
    {
        return new OrderDeliveryLines($factory, $this->module(), new OrderDeliveryStore($factory), new CodPayments($factory));
    }

    private function backend(): BackendExtender
    {
        $factory = $this->entityFactory();
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturn(null);

        return new BackendExtender(
            $this->design(),
            new OrderDeliveryStore($factory),
            new CodPayments($factory),
            $factory,
            // Чернетки без конструктора: тест, що дійшов би до форми нової накладної, впаде.
            new WaybillView($factory, new OrderDeliveryStore($factory), new WaybillStore($factory), self::blank(WaybillDrafts::class), new CodPayments($factory)),
            $this->module(),
            new ShippingSettings($settings, $factory),
            new WaybillStore($factory),
            $this->createStub(BackendTranslations::class),
            self::blank(Request::class),
            self::blank(OrdersMassAction::class),
            self::blank(OrderRowsCleanup::class),
            $this->lines($factory),
        );
    }

    /** OrdersHelper::getOrderPaymentMethodsList — сторінка замовлення покупця. */
    public function testOrderPageHidesPointOfSwitchedDelivery(): void
    {
        $this->order(self::FOREIGN_DELIVERY);
        $this->front()->assignOrderDelivery(null, $this->order);

        self::assertArrayHasKey('np_order_delivery', $this->assigned);
        self::assertNull($this->assigned['np_order_delivery']);
    }

    /** NotifyHelper::finalEmailOrderUser / finalEmailOrderAdmin — той самий extender із замовленням листа. */
    public function testEmailsHidePointOfSwitchedDelivery(): void
    {
        $this->order(self::FOREIGN_DELIVERY);
        $front = $this->front();
        foreach (['user', 'admin'] as $email) {
            $this->assigned = [];
            $front->assignOrderDelivery(null, $this->order);
            self::assertArrayHasKey('np_order_delivery', $this->assigned, $email);
            self::assertNull($this->assigned['np_order_delivery'], $email);
        }
    }

    public function testAdminHidesPointOfSwitchedDelivery(): void
    {
        $this->order(self::FOREIGN_DELIVERY);
        $this->backend()->assignOrderDelivery($this->order);

        self::assertNull($this->assigned['np_order_delivery']);
        self::assertNull($this->assigned['np_order_point']);
        self::assertNull($this->assigned['np_waybill'], 'no waybill and not ours: no form for a new one to the old point');
    }

    /** Накладна, створена до зміни способу, лишається видимою: її треба видалити в НП чи відв'язати. */
    public function testAdminKeepsShowingAnExistingWaybillAfterTheSwitch(): void
    {
        $this->order(self::FOREIGN_DELIVERY);
        $this->waybill = (object)['id' => 1, 'order_id' => 77, 'number' => '20450000000001', 'ref' => null, 'api' => 'new', 'status_code' => 1, 'status_text' => '', 'tracking' => null, 'services' => '[]', 'audit_status' => null, 'audit' => null, 'created' => '2026-11-01 10:00:00', 'updated' => '2026-11-01 10:00:00', 'checked_at' => null];
        $this->backend()->assignOrderDelivery($this->order);

        self::assertNull($this->assigned['np_order_delivery']);
        self::assertSame('20450000000001', $this->assigned['np_waybill']['row']['number']);
    }

    public function testControlOwnDeliveryStillShowsPoint(): void
    {
        $this->order(self::OWN_DELIVERY);
        $this->front()->assignOrderDelivery(null, $this->order);
        self::assertSame('село Лісове, Тестова', $this->assigned['np_order_delivery']['city']);
        self::assertSame(21.5, $this->assigned['np_order_delivery']['codFee']);

        $this->assigned = [];
        $this->waybill = (object)['id' => 1, 'order_id' => 77, 'number' => '20450000000001', 'ref' => null, 'api' => 'new', 'status_code' => 1, 'status_text' => '', 'tracking' => null, 'services' => '[]', 'audit_status' => null, 'audit' => null, 'created' => '2026-11-01 10:00:00', 'updated' => '2026-11-01 10:00:00', 'checked_at' => null];
        $this->backend()->assignOrderDelivery($this->order);
        self::assertSame('село Лісове, Тестова', $this->assigned['np_order_delivery']['city']);
        self::assertTrue($this->assigned['np_order_point']['locked'], 'the waybill went to this point');
        self::assertSame('20450000000001', $this->assigned['np_waybill']['row']['number']);
    }
}
