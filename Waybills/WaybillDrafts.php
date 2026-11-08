<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Core\EntityFactory;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryStore;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderParcel;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;
use Okay\Modules\Sviat\NovaPoshta\Quote\FreeDelivery;
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
        private readonly OrderParcel $parcel,
        private readonly AmountDue $amountDue,
        private readonly InvoicePaymentData $invoices,
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
        $purchases = $this->parcel->purchases($orderId);
        $division = $snapshot->division_id ? $this->entityFactory->get(DivisionEntity::class)->get((int)$snapshot->division_id) : null;
        $rate = $this->parcel->uahRate();

        return self::build(
            $order,
            $purchases,
            $snapshot,
            $division ?: null,
            $this->waybillSettings->read(),
            $this->shippingSettings->read(),
            $this->codPayments->kind((int)$order->payment_method_id),
            $rate,
            new \DateTimeImmutable(),
            fn(float $totalUah): float => $this->amountDue->onPickup($order, $totalUah),
            $this->invoices->forOrder($order),
            self::promisedFree($this->entityFactory->get(DeliveriesEntity::class)->get((int)($order->delivery_id ?? 0)) ?: null, $order, $snapshot),
        );
    }

    /** The cart showed delivery as free: a free method, the free_from threshold, or another module (a promo) zeroing the price. */
    public static function promisedFree(?object $delivery, object $order, ?object $snapshot): bool
    {
        if ($delivery === null) {
            return false;
        }
        $deliveryPrice = max(0.0, (float)($order->delivery_price ?? 0));
        $goods = (float)($order->total_price ?? 0) - (empty($order->separate_delivery) ? $deliveryPrice : 0.0);

        return empty($delivery->paid)
            || FreeDelivery::applies($delivery, $goods)
            || (($snapshot->price_source ?? null) === 'external' && $deliveryPrice <= 0.0);
    }

    /**
     * @param float $uahRate hryvnias per unit of the shop's main currency (order sums are in it)
     * @param ?\Closure(float): float $amountDue what NP collects, from the order total in hryvnias
     * @param ?array{edrpou: string, company: string} $invoice the organization the order was invoiced to
     * @param bool $promisedFree see promisedFree(): with the "auto" payer the shop then pays Nova Poshta
     * @return WaybillDraft|string the draft, or the language key of what the order lacks
     */
    public static function build(
        object $order,
        array $purchases,
        object $snapshot,
        ?object $division,
        WaybillConfig $waybill,
        ShippingConfig $shipping,
        ?CodKind $codKind,
        float $uahRate,
        \DateTimeImmutable $now,
        ?\Closure $amountDue = null,
        ?array $invoice = null,
        bool $promisedFree = false,
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
        $deliveryInTotal = empty($order->separate_delivery) ? max(0.0, (float)($order->delivery_price ?? 0)) : 0.0;
        $goods = $total - $deliveryInTotal;
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
            // Delivery taken in the total, or promised free: NP must not charge the recipient.
            $waybill->payerType === 'auto' ? ($deliveryInTotal > 0 || $promisedFree ? 'Sender' : 'Recipient') : $waybill->payerType,
            $waybill->paymentMethod,
            $codKind !== null && empty($order->paid),
            round($amountDue ? $amountDue($totalUah) : $totalUah),
            $codKind ?? CodKind::Money,
            $waybill->codPayer,
            ShipDate::for($now, $waybill->cutoff),
            $invoice !== null ? 'Organization' : 'PrivatePerson',
            $invoice !== null ? preg_replace('/\D+/', '', $invoice['edrpou']) : null,
            $invoice !== null ? mb_substr($invoice['company'], 0, 100) : null,
            $invoice !== null,
            round($deliveryInTotal * $uahRate, 2),
        );
    }
}
