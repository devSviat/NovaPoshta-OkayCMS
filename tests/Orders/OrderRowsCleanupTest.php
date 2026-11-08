<?php

namespace Modules\Sviat\NovaPoshta\Orders;

use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity;
use Okay\Modules\Sviat\NovaPoshta\Init\Init;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderRowsCleanup;
use PHPUnit\Framework\TestCase;

/** A deleted order takes its delivery choice and waybill row with it; Nova Poshta is not asked. */
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
