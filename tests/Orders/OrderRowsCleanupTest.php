<?php

namespace Modules\Sviat\NovaPoshta\Orders;

require_once __DIR__ . '/../Support/InMemoryState.php';

use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Core\EntityFactory;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity;
use Okay\Modules\Sviat\NovaPoshta\Init\Init;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderRowsCleanup;
use PHPUnit\Framework\TestCase;

/** Видалене замовлення забирає з собою свій вибір доставки й рядок накладної; Нову Пошту не питають. */
class OrderRowsCleanupTest extends TestCase
{
    public function testRowsOfDeletedOrdersGo(): void
    {
        $deleted = [];
        $entity = function (string $class, array $rows) use (&$deleted) {
            $e = $this->createStub($class);
            $e->method('noLimit')->willReturnSelf();
            $e->method('cols')->willReturnSelf();
            $e->method('find')->willReturnCallback(static fn(array $f): array => array_values(array_map(static fn(array $r): int => $r['id'], array_filter($rows, static fn(array $r): bool => in_array($r['order_id'], $f['order_id'], true)))));
            $e->method('delete')->willReturnCallback(static function ($ids) use (&$deleted, $class): bool {
                $deleted[$class] = (array)$ids;

                return true;
            });

            return $e;
        };
        $delivery = $entity(OrderDeliveryEntity::class, [['id' => 5, 'order_id' => 10], ['id' => 6, 'order_id' => 11], ['id' => 7, 'order_id' => 12]]);
        $waybill = $entity(WaybillEntity::class, [['id' => 8, 'order_id' => 10]]);
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(static fn(string $c) => $c === WaybillEntity::class ? $waybill : $delivery);

        new OrderRowsCleanup($factory)->forOrders(['10', 11]);

        self::assertSame([5, 6], $deleted[OrderDeliveryEntity::class]);
        self::assertSame([8], $deleted[WaybillEntity::class]);
    }

    /**
     * Замовлення, видалені, поки модуль був вимкнений, зберігають свої рядки: раз на день їх прибирають, і трекінг
     * перестає питати про них НП.
     */
    public function testRowsWithoutAnOrderGoOnceADay(): void
    {
        $deleted = [];
        $rows = static fn(array $list): array => array_map(static fn(array $r): object => (object)$r, $list);
        $entity = function (string $class, array $list) use (&$deleted, $rows) {
            $e = $this->createStub($class);
            $e->method('noLimit')->willReturnSelf();
            $e->method('cols')->willReturnSelf();
            $e->method('find')->willReturn($rows($list));
            $e->method('delete')->willReturnCallback(static function ($ids) use (&$deleted, $class): bool {
                $deleted[$class] = (array)$ids;

                return true;
            });

            return $e;
        };
        $delivery = $entity(OrderDeliveryEntity::class, [['id' => 5, 'order_id' => 10], ['id' => 6, 'order_id' => 11]]);
        $waybill = $entity(WaybillEntity::class, [['id' => 8, 'order_id' => 11], ['id' => 9, 'order_id' => 12]]);
        $orders = $this->createStub(OrdersEntity::class);
        $orders->method('noLimit')->willReturnSelf();
        $orders->method('cols')->willReturnSelf();
        $orders->method('find')->willReturnCallback(static fn(array $f): array => array_values(array_intersect([10], $f['id'])));
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(static fn(string $c) => match ($c) {
            WaybillEntity::class => $waybill,
            OrdersEntity::class => $orders,
            default => $delivery,
        });
        $state = new InMemoryState();
        $cleanup = new OrderRowsCleanup($factory, $state, static fn(): string => '2026-11-09');

        $cleanup->tick();

        self::assertSame([6], $deleted[OrderDeliveryEntity::class]);
        self::assertSame([8, 9], $deleted[WaybillEntity::class]);
        $deleted = [];
        $cleanup->tick();
        self::assertSame([], $deleted, 'once a day');
        new OrderRowsCleanup($factory, $state, static fn(): string => '2026-11-10')->tick();
        self::assertSame([6], $deleted[OrderDeliveryEntity::class], 'the next day again');
    }

    public function testNothingToDelete(): void
    {
        $factory = $this->createMock(EntityFactory::class);
        $factory->expects(self::never())->method('get');

        new OrderRowsCleanup($factory)->forOrders([]);
    }

    public function testHookedToTheOrderDelete(): void
    {
        $source = (string)file_get_contents((new \ReflectionClass(Init::class))->getFileName());

        self::assertStringContainsString("[BackendOrdersHelper::class, 'delete'], [BackendExtender::class, 'deleteOrders']", $source);
    }
}
