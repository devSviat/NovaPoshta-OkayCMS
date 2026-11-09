<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Core\EntityFactory;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Groups\DivisionCategory;
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
     * @param bool $promisedFree see promisedFree(): the shop then pays Nova Poshta, whatever the setting
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
            // Paid lifting sends the floor in its own field; without it NP has none, and the courier needs it.
            empty($snapshot->lifting) && (int)($snapshot->floor ?? 0) > 0 ? 'поверх ' . (int)$snapshot->floor : null,
            trim((string)($snapshot->address_note ?? '')) ?: null,
        ]));
        $phone = NpPhone::normalize((string)($order->phone ?? ''));
        $name = trim(preg_replace('/\s+/u', ' ', trim((string)($order->last_name ?? '')) . ' ' . trim((string)($order->name ?? ''))));
        // The form refuses a longer name; a mass run has no form. NP takes a cut word for an abbreviation and refuses it.
        if (mb_strlen($name) > WaybillDraft::NAME_MAX) {
            $cut = mb_substr($name, 0, WaybillDraft::NAME_MAX + 1);
            $space = mb_strrpos($cut, ' ');
            $name = $space !== false ? rtrim(mb_substr($cut, 0, $space)) : mb_substr($name, 0, WaybillDraft::NAME_MAX);
        }
        // What another module (a prepayment) leaves to collect; nothing left is no COD, never a «0» or negative one.
        $due = max(0.0, round($amountDue ? $amountDue($totalUah) : $totalUah));
        $cod = $codKind !== null && empty($order->paid) && $due > 0;
        $address = $mode === DeliveryMode::Address;
        $locker = !$address && ($division->category ?? null) === DivisionCategory::Postomat->value;
        // Delivery the shop already took (in the total) or promised free: NP must not charge the recipient.
        $senderPaysBecause = $deliveryInTotal > 0 ? 'in_total' : ($promisedFree ? 'free' : null);

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
            $waybill->descriptionMode === 'text' ? $waybill->description : 'Замовлення №' . (int)$order->id,
            // A parcel locker takes no «Вантаж» (api-docs-ua, «ЕН на поштомат»).
            $locker && $waybill->cargoType === 'Cargo' ? 'Parcel' : $waybill->cargoType,
            $senderPaysBecause !== null ? 'Sender' : $waybill->payerType,
            $waybill->paymentMethod,
            $cod,
            $due,
            $codKind ?? CodKind::Money,
            $waybill->codPayer,
            ShipDate::for($now, $waybill->cutoff),
            $invoice !== null ? 'Organization' : 'PrivatePerson',
            $invoice !== null ? preg_replace('/\D+/', '', $invoice['edrpou']) : null,
            $invoice !== null ? mb_substr($invoice['company'], 0, 100) : null,
            $invoice !== null,
            round($deliveryInTotal * $uahRate, 2),
            self::extraInfo($purchases, $waybill->extraInfo),
            $senderPaysBecause,
            $locker,
            cargoTypes: $waybill->cargoTypes,
            awaitsPayment: $codKind === null && empty($order->paid),
        );
    }

    /**
     * AdditionalInformation as the old module made it: SKUs (a name where there is none), «(2 шт)» for
     * several, cut to NP's 100 characters.
     */
    public static function extraInfo(array $purchases, string $kind): string
    {
        if ($kind === 'none') {
            return '';
        }
        $counts = [];
        foreach ($purchases as $purchase) {
            $sku = trim((string)($purchase->sku ?? '')) ?: trim((string)($purchase->variant->sku ?? ''));
            $name = trim((string)($purchase->product_name ?? ''));
            $item = $kind === 'names' ? ($name ?: $sku) : ($sku ?: $name);
            if ($item !== '') {
                $counts[$item] = ($counts[$item] ?? 0) + max(1, (int)($purchase->amount ?? 1));
            }
        }
        $items = array_map(static fn(string $item, int $n): string => $n > 1 ? "{$item} ({$n} шт)" : $item, array_keys($counts), $counts);

        return mb_substr(implode(', ', $items), 0, WaybillDraft::EXTRA_INFO_MAX);
    }
}
