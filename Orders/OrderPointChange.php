<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartSelection;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartSelectionValidator;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsStore;
use Okay\Modules\Sviat\NovaPoshta\Quote\CartQuotes;
use Okay\Modules\Sviat\NovaPoshta\Quote\DeliveryQuote;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillRows;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillService;

/** The manager changes the point or address of an order in the admin (stage 4a2). */
final class OrderPointChange
{
    /** A point that does not take the parcel is still the manager's call (design §6). */
    private const array FIT_PROBLEMS = ['error_point_too_heavy', 'error_point_too_big', 'error_point_too_expensive'];

    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly Module $module,
        private readonly GroupsStore $groups,
        private readonly OrderDeliveryStore $snapshots,
        private readonly OrderParcel $parcel,
        private readonly CartQuotes $quotes,
        private readonly ShippingSettings $shipping,
        private readonly WaybillRows $waybills,
    ) {
    }

    /** @param array<string, string> $input settlement_id with division_id, or the address fields */
    public function save(int $orderId, array $input): OrderPointResult
    {
        $order = $this->entityFactory->get(OrdersEntity::class)->get($orderId);
        $delivery = $order ? $this->delivery((int)$order->delivery_id) : null;
        if (!$order || !$delivery) {
            return OrderPointResult::failed('order_point_foreign');
        }
        $settlementId = (int)($input['settlement_id'] ?? 0);
        $divisionId = (int)($input['division_id'] ?? 0);
        $settlement = $settlementId > 0 ? $this->entityFactory->get(SettlementEntity::class)->get($settlementId) : null;
        $division = $divisionId > 0 ? $this->entityFactory->get(DivisionEntity::class)->get($divisionId) : null;
        $chosen = self::select(
            $delivery,
            (int)$this->module->getModuleIdByNamespace(__CLASS__),
            $settlement ?: null,
            $division ?: null,
            $this->groups->all(),
            $this->parcel->metrics($order),
            $this->waybills->forOrder($orderId),
            $input,
            $this->shipping->read()->showLifting,
            time(),
        );
        if ($chosen['error'] !== null) {
            return OrderPointResult::failed($chosen['error']);
        }
        [$price, $quote] = $this->price($order, $delivery, $chosen['selection']);
        $this->snapshots->save($orderId, $chosen['selection'], self::snapshotPricing($quote));
        if ($price['action'] === 'auto') {
            $this->writePrice($orderId, (float)$price['price']);
        }

        return new OrderPointResult(true, null, $chosen['warning'], $price);
    }

    /** Writes the tariff to the order's current point; only when the rule offers it, never a posted amount. */
    public function applyPrice(int $orderId): OrderPointResult
    {
        $order = $this->entityFactory->get(OrdersEntity::class)->get($orderId);
        $delivery = $order ? $this->delivery((int)$order->delivery_id) : null;
        $snapshot = $order ? $this->snapshots->forOrder($orderId) : null;
        if (!$order || !$delivery || !$snapshot) {
            return OrderPointResult::failed('order_point_foreign');
        }
        $settlement = $this->entityFactory->get(SettlementEntity::class)->get((int)$snapshot->settlement_id);
        $division = $snapshot->division_id ? $this->entityFactory->get(DivisionEntity::class)->get((int)$snapshot->division_id) : null;
        $address = null;
        if ($snapshot->mode === DeliveryMode::Address->value) {
            $address = (object)['lifting' => !empty($snapshot->lifting), 'floor' => $snapshot->floor ? (int)$snapshot->floor : null, 'elevator' => !empty($snapshot->elevator)];
        }
        [$price] = $this->price($order, $delivery, new CartSelection($delivery, $settlement ?: null, $division ?: null, null), $address);
        if ($price['action'] !== 'offer') {
            return new OrderPointResult(false, 'order_point_price_not_offered', null, $price);
        }
        $this->writePrice($orderId, (float)$price['price']);

        return new OrderPointResult(true, null, null, ['action' => 'applied', 'price' => $price['price']]);
    }

    /**
     * Checks a chosen point or address like the cart does; a point that does not take the parcel
     * passes with a warning.
     *
     * @return array{selection: ?CartSelection, error: ?string, warning: ?string}
     */
    public static function select(object $delivery, int $moduleId, ?object $settlement, ?object $division, array $groupRows, CartMetrics $metrics, ?object $waybill, array $input, bool $liftingOffered, int $now): array
    {
        $fail = static fn(string $error): array => ['selection' => null, 'error' => $error, 'warning' => null];
        if (self::locks($waybill, $now)) {
            return $fail('order_point_locked');
        }
        if ((int)($delivery->module_id ?? 0) !== $moduleId || $moduleId === 0) {
            return $fail('order_point_foreign');
        }
        $settings = is_array($delivery->settings ?? null) ? $delivery->settings : [];
        if (DeliveryMode::fromServiceType($settings['service_type'] ?? null) === DeliveryMode::Address) {
            $fields = array_map(static fn($v): string => is_scalar($v) ? (string)$v : '', $input);
            $checked = CartSelectionValidator::checkAddress($delivery, $moduleId, $settlement, ['novaposhta' => [(int)$delivery->id => $fields]], $liftingOffered);

            return is_string($checked) ? $fail($checked) : ['selection' => $checked, 'error' => null, 'warning' => null];
        }

        $checked = CartSelectionValidator::check($delivery, $moduleId, $settlement, $division, $groupRows, $metrics);
        if ($checked instanceof CartSelection) {
            return ['selection' => $checked, 'error' => null, 'warning' => null];
        }
        if (in_array($checked, self::FIT_PROBLEMS, true)) {
            return ['selection' => new CartSelection($delivery, $settlement, $division), 'error' => null, 'warning' => $checked];
        }

        return $fail($checked);
    }

    /**
     * A made or attached waybill locks the point, and so does one being made right now (its draft has
     * the old point). A reservation left by a request that died does not.
     */
    public static function locks(?object $waybill, int $now): bool
    {
        return $waybill !== null && ($waybill->api !== 'pending' || WaybillService::isPending($waybill, $now));
    }

    /**
     * What the order page's block shows for the order's delivery method `$mode`. After the method was
     * switched, a waybill made for the old point stays visible: it has to be deleted from there.
     *
     * @return array{needsChoice: bool, locked: bool, open: bool, showWaybill: bool}
     */
    public static function editorState(?object $snapshot, string $mode, ?object $waybill, int $now): array
    {
        $needsChoice = $snapshot === null || $snapshot->mode !== $mode;
        $locked = self::locks($waybill, $now);

        return [
            'needsChoice' => $needsChoice,
            'locked' => $locked,
            'open' => $needsChoice && !$locked,
            'showWaybill' => $snapshot !== null && (!$needsChoice || ($waybill !== null && $waybill->api !== 'pending')),
        ];
    }

    /** Term and COD commission of the new point; the old point's would reach the emails otherwise. */
    public static function snapshotPricing(?DeliveryQuote $quote): array
    {
        return ['delivery_term' => $quote?->term?->format('Y-m-d'), 'cod_fee' => $quote?->codFee];
    }

    /** @return array{0: array{action: string, price: ?float}, 1: ?DeliveryQuote} */
    private function price(object $order, object $delivery, CartSelection $selection, ?object $snapshotAddress = null): array
    {
        $cart = OrderParcel::asCart($order, $this->parcel->purchases((int)$order->id));
        $paymentId = (int)($order->payment_method_id ?? 0);
        $address = $selection->address ?? $snapshotAddress;
        $quote = match (true) {
            $address !== null && $selection->settlement !== null => $this->quotes->quoteAddress($delivery, $selection->settlement, (bool)$address->lifting, $address->floor, (bool)$address->elevator, $paymentId, $cart, forAdmin: true),
            $selection->division !== null => $this->quotes->quote($delivery, (int)$selection->division->id, $paymentId, $cart, forAdmin: true),
            default => null,
        };

        return [OrderPriceRule::decide($order, $delivery, $quote?->price, !$quote instanceof DeliveryQuote || $quote->price === null), $quote];
    }

    private function writePrice(int $orderId, float $price): void
    {
        $orders = $this->entityFactory->get(OrdersEntity::class);
        $orders->update($orderId, ['delivery_price' => $price]);
        $orders->updateTotalPrice($orderId);
        $this->snapshots->setPrice($orderId, $price, 'admin');
    }

    private function delivery(int $deliveryId): ?object
    {
        $delivery = $deliveryId > 0 ? $this->entityFactory->get(DeliveriesEntity::class)->get($deliveryId) : null;
        if ($delivery && is_string($delivery->settings ?? null)) {
            $delivery->settings = @unserialize($delivery->settings, ['allowed_classes' => false]) ?: [];
        }

        return $delivery ?: null;
    }
}
