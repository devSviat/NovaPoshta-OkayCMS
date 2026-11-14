<?php

namespace Modules\Sviat\NovaPoshta\Orders;

require_once __DIR__ . '/../Support/InMemoryState.php';

use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity;
use Okay\Modules\Sviat\NovaPoshta\Init\Init;
use Okay\Modules\Sviat\NovaPoshta\Orders\ExistingOrders;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderRowsCleanup;
use PHPUnit\Framework\TestCase;

/** Видалене замовлення забирає з собою свій вибір доставки й рядок накладної; Нову Пошту не питають. */
class OrderRowsCleanupTest extends TestCase
{
    private const string TODAY = '2026-11-09';

    /** @var array<string, list<int>> */
    private array $deleted = [];
    private InMemoryState $state;

    protected function setUp(): void
    {
        $this->state = new InMemoryState();
    }

    private static function orders(?array $existing, ?int $maxId): ExistingOrders
    {
        return new class ($existing, $maxId) implements ExistingOrders {
            public function __construct(private ?array $existing, private ?int $max)
            {
            }

            public function among(array $orderIds): ?array
            {
                return $this->existing;
            }

            public function maxId(): ?int
            {
                return $this->max;
            }
        };
    }

    /** Рядки вибору доставки замовлень 10, 11, 40 і накладні замовлень 11, 12. */
    private function factory(): EntityFactory
    {
        $entity = function (string $class, array $list) {
            $e = $this->createStub($class);
            $e->method('noLimit')->willReturnSelf();
            $e->method('cols')->willReturnSelf();
            $e->method('find')->willReturnCallback(static fn(array $filter = []): array => isset($filter['order_id'])
                ? array_values(array_map(static fn(array $r): int => $r['id'], array_filter($list, static fn(array $r): bool => in_array($r['order_id'], $filter['order_id'], true))))
                : array_map(static fn(array $r): object => (object)$r, $list));
            $e->method('delete')->willReturnCallback(function ($ids) use ($class): bool {
                $this->deleted[$class] = array_values((array)$ids);

                return true;
            });

            return $e;
        };
        $delivery = $entity(OrderDeliveryEntity::class, [['id' => 5, 'order_id' => 10], ['id' => 6, 'order_id' => 11], ['id' => 7, 'order_id' => 40]]);
        $waybill = $entity(WaybillEntity::class, [['id' => 8, 'order_id' => 11], ['id' => 9, 'order_id' => 12]]);
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(static fn(string $c) => $c === WaybillEntity::class ? $waybill : $delivery);

        return $factory;
    }

    private function tick(?array $existing, ?int $maxId, string $today = self::TODAY): void
    {
        new OrderRowsCleanup($this->factory(), $this->state, self::orders($existing, $maxId), static fn(): string => $today)->tick();
    }

    public function testRowsOfDeletedOrdersGo(): void
    {
        new OrderRowsCleanup($this->factory(), $this->state, self::orders(null, null))->forOrders(['10', 11]);

        self::assertSame([5, 6], $this->deleted[OrderDeliveryEntity::class]);
        self::assertSame([8], $this->deleted[WaybillEntity::class]);
    }

    /**
     * Замовлення, видалені, поки модуль був вимкнений, зберігають свої рядки: раз на день їх прибирають, і трекінг
     * перестає питати про них НП.
     */
    public function testRowsWithoutAnOrderGoOnceADay(): void
    {
        $this->tick([10 => true, 40 => true], 50);

        self::assertSame([6], $this->deleted[OrderDeliveryEntity::class]);
        self::assertSame([8, 9], $this->deleted[WaybillEntity::class]);
        self::assertSame(self::TODAY, $this->state->get('orphan_rows_checked_on'));
        $this->deleted = [];
        $this->tick([10 => true, 40 => true], 50);
        self::assertSame([], $this->deleted, 'once a day');
        $this->tick([10 => true, 40 => true], 50, '2026-11-10');
        self::assertSame([6], $this->deleted[OrderDeliveryEntity::class], 'the next day again');
    }

    /** Database ковтає помилку запиту: без відповіді про замовлення жоден рядок і жодна накладна не зникають. */
    public function testFailedLookupKeepsEveryRowAndRetries(): void
    {
        $this->tick(null, 50);

        self::assertSame([], $this->deleted);
        self::assertNull($this->state->get('orphan_rows_checked_on'), 'the next tick tries again');
    }

    public function testEmptyLookupKeepsEveryRow(): void
    {
        $this->tick([], 50);

        self::assertSame([], $this->deleted);
    }

    public function testFailedMaxIdKeepsEveryRow(): void
    {
        $this->tick([10 => true], null);

        self::assertSame([], $this->deleted);
        self::assertNull($this->state->get('orphan_rows_checked_on'));
    }

    /** Замовлення з номером, більшим за найбільший видимий, ще не видно, а не видалено. */
    public function testOrderAboveTheMaxIdIsKept(): void
    {
        $this->tick([10 => true], 11);

        self::assertSame([6], $this->deleted[OrderDeliveryEntity::class]);
        self::assertSame([8], $this->deleted[WaybillEntity::class]);
    }

    public function testNothingToDelete(): void
    {
        $factory = $this->createMock(EntityFactory::class);
        $factory->expects(self::never())->method('get');

        new OrderRowsCleanup($factory, $this->state, self::orders(null, null))->forOrders([]);
    }

    public function testHookedToTheOrderDelete(): void
    {
        $source = (string)file_get_contents((new \ReflectionClass(Init::class))->getFileName());

        self::assertStringContainsString("[BackendOrdersHelper::class, 'delete'], [BackendExtender::class, 'deleteOrders']", $source);
    }
}
