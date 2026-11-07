<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Core\EntityFactory;
use Okay\Core\Money;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Entities\VariantsEntity;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryStore;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;
use Okay\Modules\Sviat\NovaPoshta\Quote\Parcel;
use Okay\Modules\Sviat\NovaPoshta\Quote\ShippingConfig;

/** Builds the first draft of an order's waybill. */
final class WaybillDrafts
{
    private const int NOTE_MAX = 50;

    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly OrderDeliveryStore $snapshots,
        private readonly WaybillSettings $waybillSettings,
        private readonly ShippingSettings $shippingSettings,
        private readonly CodPayments $codPayments,
        private readonly Money $money,
        private readonly AmountDue $amountDue,
    ) {
    }

    /** @return WaybillDraft|string the draft, or the language key of what the order lacks */
    public function forOrder(int $orderId): WaybillDraft|string
    {
        $order = $this->entityFactory->get(OrdersEntity::class)->get($orderId);
        $snapshot = $order ? $this->snapshots->forOrder($orderId) : null;
        if (!$order || !$snapshot) {
            return 'waybill_no_snapshot';
        }
        $purchases = $this->entityFactory->get(PurchasesEntity::class)->find(['order_id' => $orderId]);
        $variantIds = array_values(array_filter(array_map(static fn($p): int => (int)$p->variant_id, $purchases)));
        $variants = $variantIds === [] ? [] : $this->entityFactory->get(VariantsEntity::class)->mappedBy('id')->find(['id' => $variantIds]);
        foreach ($purchases as $purchase) {
            $purchase->variant = $variants[(int)$purchase->variant_id] ?? null;
        }
        $division = $snapshot->division_id ? $this->entityFactory->get(DivisionEntity::class)->get((int)$snapshot->division_id) : null;
        $uah = $this->entityFactory->get(CurrenciesEntity::class)->findOne(['code' => 'UAH']);
        $rate = $uah ? (float)$this->money->convert(1, (int)$uah->id, false, false, 6) : 1.0;

        return self::build(
            $order,
            $purchases,
            $snapshot,
            $division ?: null,
            $this->waybillSettings->read(),
            $this->shippingSettings->read(),
            $this->codPayments->isCod((int)$order->payment_method_id),
            $rate,
            new \DateTimeImmutable(),
            fn(float $totalUah): float => $this->amountDue->onPickup($order, $totalUah),
        );
    }

    /**
     * @param float $uahRate hryvnias per unit of the shop's main currency (order sums are in it)
     * @param ?\Closure(float): float $amountDue what NP collects, from the order total in hryvnias
     * @return WaybillDraft|string the draft, or the language key of what the order lacks
     */
    public static function build(
        object $order,
        array $purchases,
        object $snapshot,
        ?object $division,
        WaybillConfig $waybill,
        ShippingConfig $shipping,
        bool $codPayment,
        float $uahRate,
        \DateTimeImmutable $now,
        ?\Closure $amountDue = null,
    ): WaybillDraft|string {
        $mode = DeliveryMode::tryFrom((string)($snapshot->mode ?? '')) ?? DeliveryMode::Points;
        if ($mode === DeliveryMode::Points) {
            if (empty($snapshot->division_id)) {
                return 'waybill_no_point';
            }
            if ($division === null) {
                return 'waybill_point_gone';
            }
        } elseif (trim((string)($snapshot->street_name ?? '')) === '' || trim((string)($snapshot->house ?? '')) === '') {
            return 'waybill_no_address';
        }

        $total = (float)($order->total_price ?? 0);
        $goods = empty($order->separate_delivery) ? $total - (float)($order->delivery_price ?? 0) : $total;
        $totalUah = round($total * $uahRate, 2);
        $parcel = Parcel::fromMetrics(CartMetrics::fromPurchases($purchases, $total), $shipping);
        $note = implode(', ', array_filter([
            trim((string)($snapshot->entrance ?? '')) !== '' ? "під'їзд " . trim((string)$snapshot->entrance) : null,
            trim((string)($snapshot->address_note ?? '')) ?: null,
        ]));
        $phone = NpPhone::normalize((string)($order->phone ?? ''));
        $name = trim(preg_replace('/\s+/u', ' ', trim((string)($order->last_name ?? '')) . ' ' . trim((string)($order->name ?? ''))));
        $address = $mode === DeliveryMode::Address;

        return new WaybillDraft(
            (int)$order->id,
            $mode,
            $address ? null : (int)$division->id,
            $address ? null : (string)$division->number,
            $address ? null : (string)$division->name_uk,
            isset($snapshot->settlement_id) ? (int)$snapshot->settlement_id : null,
            (string)($snapshot->settlement_name ?? ''),
            ($snapshot->area_name ?? null) ?: null,
            ($snapshot->district_name ?? null) ?: null,
            $address ? (string)$snapshot->street_name : null,
            $address ? (($snapshot->street_ref ?? null) ?: null) : null,
            $address ? (string)$snapshot->house : null,
            $address ? (((string)($snapshot->flat ?? '')) ?: null) : null,
            $address && $note !== '' ? mb_substr($note, 0, self::NOTE_MAX) : null,
            $address && !empty($snapshot->lifting),
            $address && !empty($snapshot->floor) ? (int)$snapshot->floor : null,
            $address && !empty($snapshot->elevator),
            $name,
            $phone,
            $parcel->weightKg,
            $parcel->volumeM3,
            round(max(0.0, $goods) * $uahRate, 2),
            $waybill->description,
            $waybill->cargoType,
            $waybill->payerType,
            $waybill->paymentMethod,
            $codPayment && empty($order->paid),
            $amountDue ? round($amountDue($totalUah), 2) : $totalUah,
            $shipping->codKind,
            $waybill->codPayer,
            ShipDate::for($now, $waybill->cutoff),
        );
    }
}
