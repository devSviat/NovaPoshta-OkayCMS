<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Core\EntityFactory;
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
    private const array TABLES = [OrderDeliveryEntity::class, WaybillEntity::class];

    private readonly \Closure $today;

    /** @param ?\Closure(): string $today Y-m-d */
    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly StateStorage $state,
        private readonly ExistingOrders $orders,
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
        if ($this->state->get(self::CHECKED_ON) === $today) {
            return;
        }
        $orderOf = [];
        $orderIds = [];
        foreach (self::TABLES as $class) {
            $orderOf[$class] = [];
            foreach ($this->entityFactory->get($class)->noLimit()->cols(['id', 'order_id'])->find() as $row) {
                $orderOf[$class][(int)$row->id] = (int)$row->order_id;
                $orderIds[(int)$row->order_id] = true;
            }
        }
        if ($orderIds !== []) {
            $existing = $this->orders->among(array_keys($orderIds));
            $maxOrderId = $this->orders->maxId();
            $orphans = [];
            foreach (self::TABLES as $class) {
                $orphans[$class] = self::orphans($orderOf[$class], $existing, $maxOrderId);
                // Без відповіді про замовлення не видаляємо нічого й не позначаємо день: наступний тік спробує знову.
                if ($orphans[$class] === null) {
                    return;
                }
            }
            foreach ($orphans as $class => $ids) {
                if ($ids !== []) {
                    $this->entityFactory->get($class)->delete($ids);
                }
            }
        }
        $this->state->set(self::CHECKED_ON, $today);
    }

    /**
     * Рядок без замовлення — сирота лише тоді, коли відповідь про замовлення є й правдоподібна. Порожня відповідь
     * при наявних рядках схожа радше на збій, ніж на видалення всіх замовлень; номер, більший за найбільший
     * у таблиці, належить замовленню, яке ще не видно (не видалене).
     *
     * @param array<int, int> $orderOf order_id за id рядка
     * @param ?array<int, true> $existing
     * @return ?list<int> id рядків для видалення; null — відповіді немає або вона неправдоподібна
     */
    public static function orphans(array $orderOf, ?array $existing, ?int $maxOrderId): ?array
    {
        if ($existing === null || $existing === [] || $maxOrderId === null || $maxOrderId <= 0) {
            return null;
        }

        return array_keys(array_filter(
            $orderOf,
            static fn(int $orderId): bool => !isset($existing[$orderId]) && $orderId <= $maxOrderId,
        ));
    }

    /** @param list<int|string> $orderIds */
    public function forOrders(array $orderIds): void
    {
        $orderIds = array_values(array_unique(array_filter(array_map(intval(...), $orderIds))));
        if ($orderIds === []) {
            return;
        }
        foreach (self::TABLES as $class) {
            $entity = $this->entityFactory->get($class);
            $ids = array_map(intval(...), $entity->noLimit()->cols(['id'])->find(['order_id' => $orderIds]));
            if ($ids !== []) {
                $entity->delete($ids);
            }
        }
    }
}
