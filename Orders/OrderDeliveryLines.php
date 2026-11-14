<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Entities\DeliveriesEntity;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;

/**
 * Пункт видачі чи адреса замовлення для сторінок замовлення, листів і адмінки. Коли менеджер змінив
 * доставку на спосіб іншого модуля, рядок лишається в базі (повернення доставки поверне й пункт),
 * але не показується.
 */
final class OrderDeliveryLines
{
    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly Module $module,
        private readonly OrderDeliveryStore $snapshots,
        private readonly CodPayments $codPayments,
    ) {
    }

    /** Поточний спосіб доставки замовлення, якщо він цього модуля; інакше null. */
    public function ownDelivery(object $order): ?object
    {
        $deliveryId = (int)($order->delivery_id ?? 0);
        $delivery = $deliveryId > 0 ? $this->entityFactory->get(DeliveriesEntity::class)->get($deliveryId) : null;
        $moduleId = (int)$this->module->getModuleIdByNamespace(__CLASS__);

        return $delivery && $moduleId > 0 && (int)($delivery->module_id ?? 0) === $moduleId ? $delivery : null;
    }

    /** @return ?array OrderDeliveryView::lines; null — рядка немає або доставка замовлення не цього модуля */
    public function forOrder(object $order): ?array
    {
        if (empty($order->id)) {
            return null;
        }
        $delivery = $this->ownDelivery($order);
        $row = $delivery ? $this->snapshots->forOrder((int)$order->id) : null;

        return $row === null ? null : $this->lines($order, $delivery, $row);
    }

    /** @param object $delivery поточний спосіб доставки замовлення, уже перевірений ownDelivery() */
    public function lines(object $order, object $delivery, object $row): array
    {
        return OrderDeliveryView::lines(
            $row,
            (float)($order->delivery_price ?? 0),
            $this->codPayments->isCod((int)($order->payment_method_id ?? 0)),
            empty($delivery->hide_front_delivery_price),
        );
    }
}
