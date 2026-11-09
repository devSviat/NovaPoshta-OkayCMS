<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Core\EntityFactory;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\WaybillEntity;
use Okay\Modules\Sviat\NovaPoshta\Storage\StateStorage;

/**
 * Рядки модуля для замовлень, які видаляє адмінка. Лише рядки: уже створена накладна лишається в
 * Новій Пошті — видалення замовлення не є запитом скасувати відправлення.
 */
final class OrderRowsCleanup
{
    private const string CHECKED_ON = 'orphan_rows_checked_on';
    private const int CHUNK = 500;

    private readonly \Closure $today;

    /** @param ?\Closure(): string $today Y-m-d */
    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly ?StateStorage $state = null,
        ?\Closure $today = null,
    ) {
        $this->today = $today ?? static fn(): string => date('Y-m-d');
    }

    /**
     * Планувальник: раз на день — рядки замовлень, видалених, поки модуль був вимкнений (хук видалення не спрацював).
     * Без цього трекінг і далі питає НП про них, а повторно використаний номер замовлення показав би пункт іншого покупця.
     */
    public function tick(): void
    {
        $today = ($this->today)();
        if ($this->state === null || $this->state->get(self::CHECKED_ON) === $today) {
            return;
        }
        $this->state->set(self::CHECKED_ON, $today);
        foreach ([OrderDeliveryEntity::class, WaybillEntity::class] as $class) {
            $entity = $this->entityFactory->get($class);
            $orderOf = [];
            foreach ($entity->noLimit()->cols(['id', 'order_id'])->find() as $row) {
                $orderOf[(int)$row->id] = (int)$row->order_id;
            }
            $existing = $this->existingOrders(array_values(array_unique($orderOf)));
            $orphans = array_keys(array_filter($orderOf, static fn(int $orderId): bool => !isset($existing[$orderId])));
            if ($orphans !== []) {
                $entity->delete($orphans);
            }
        }
    }

    /**
     * @param list<int> $orderIds
     * @return array<int, true>
     */
    private function existingOrders(array $orderIds): array
    {
        $orders = $this->entityFactory->get(OrdersEntity::class);
        $existing = [];
        foreach (array_chunk($orderIds, self::CHUNK) as $chunk) {
            foreach ($orders->noLimit()->cols(['id'])->find(['id' => $chunk]) as $id) {
                $existing[(int)$id] = true;
            }
        }

        return $existing;
    }

    /** @param list<int|string> $orderIds */
    public function forOrders(array $orderIds): void
    {
        $orderIds = array_values(array_unique(array_filter(array_map(intval(...), $orderIds))));
        if ($orderIds === []) {
            return;
        }
        foreach ([OrderDeliveryEntity::class, WaybillEntity::class] as $class) {
            $entity = $this->entityFactory->get($class);
            $ids = array_map(intval(...), $entity->noLimit()->cols(['id'])->find(['order_id' => $orderIds]));
            if ($ids !== []) {
                $entity->delete($ids);
            }
        }
    }
}
