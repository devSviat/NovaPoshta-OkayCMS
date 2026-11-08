<?php

namespace Okay\Modules\Sviat\NovaPoshta\Extenders;

use Okay\Modules\Sviat\NovaPoshta\Waybills\PendingWaybill;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Entities\DeliveriesEntity;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryStore;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryView;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPaymentInput;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;
use Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillView;
use Okay\Core\Modules\Module;
use Okay\Core\BackendTranslations;
use Okay\Entities\CurrenciesEntity;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStore;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderPointChange;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrdersListFilter;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStatus;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrdersMassAction;

class BackendExtender implements ExtensionInterface
{
    public function __construct(
        private readonly Design $design,
        private readonly OrderDeliveryStore $orders,
        private readonly CodPayments $codPayments,
        private readonly EntityFactory $entityFactory,
        private readonly WaybillView $waybills,
        private readonly Module $module,
        private readonly ShippingSettings $shipping,
        private readonly WaybillStore $waybillRows,
        private readonly BackendTranslations $translations,
        private readonly Request $request,
        private readonly OrdersMassAction $massAction,
    ) {
    }

    /** ChainExtension of BackendPaymentsRequest::postPayment: the core stores the field with the rest of the payment. */
    public function paymentPost($paymentMethod)
    {
        if (is_object($paymentMethod)) {
            $post = [];
            foreach ([CodPaymentInput::MARKER, CodPaymentInput::SWITCH, CodPaymentInput::KIND] as $name) {
                $post[$name] = $this->request->post($name);
            }
            $flag = CodPaymentInput::flag($post, $paymentMethod->module ?? null);
            if ($flag !== null) {
                $paymentMethod->{CodPayments::FIELD} = $flag;
            }
        }

        return $paymentMethod;
    }

    /** QueueExtension of BackendPaymentsHelper::getPaymentMethod: what payment_cod.tpl shows. */
    public function assignPaymentCod($payment, $id = null)
    {
        $this->design->assign('np_codpay', [
            'kind' => is_object($payment) ? $this->codPayments->kindOfPayment($payment)?->value : null,
            'manual' => CodPayments::isManual(is_object($payment) ? ($payment->module ?? null) : null),
        ]);
    }

    /** QueueExtension of BackendOrdersHelper::findOrder: the order page shows where the parcel goes. */
    public function assignOrderDelivery($order, $orderId = null)
    {
        $row = is_object($order) && !empty($order->id) ? $this->orders->forOrder((int)$order->id) : null;
        $point = is_object($order) && !empty($order->id) ? $this->pointEditor($order, $row) : null;
        $showWaybill = $point === null ? $row !== null : $point['showWaybill'];
        if ($point !== null && $point['needsChoice']) {
            // The saved point is of the other mode (delivery method changed) or there is none yet.
            $row = null;
        }
        $this->design->assign('np_order_point', $point);
        $lines = null;
        if ($row) {
            $delivery = $this->entityFactory->get(DeliveriesEntity::class)->get((int)($order->delivery_id ?? 0));
            $lines = OrderDeliveryView::lines(
                $row,
                (float)($order->delivery_price ?? 0),
                $this->codPayments->isCod((int)($order->payment_method_id ?? 0)),
                empty($delivery->hide_front_delivery_price),
            );
        }
        $this->design->assign('np_order_delivery', $lines);
        $this->design->assign('np_waybill', $showWaybill ? $this->waybills->forOrder((int)$order->id) : null);
    }

    /** Texts the point/address editor script shows; keys of Backend/lang without the prefix. */
    private const array EDITOR_TEXTS = [
        'op_city', 'op_city_placeholder', 'op_point_search', 'op_point_search_placeholder', 'op_street', 'op_house', 'op_flat', 'op_entrance',
        'op_note', 'op_lifting', 'op_floor', 'op_elevator', 'op_save', 'op_cancel', 'op_saving', 'op_no_points', 'op_shown_of', 'op_all_day',
        'op_weekdays', 'op_day_off', 'op_unfit_too_heavy', 'op_unfit_too_big', 'op_unfit_too_expensive', 'op_choose_point', 'op_network',
        'op_price_keep', 'op_price_same', 'op_price_free', 'op_price_show', 'op_price_auto', 'op_price_offer', 'op_price_apply', 'op_price_applied', 'op_street_typed',
    ];

    /** What the point/address editor needs; null when the order's delivery method is not this module's. */
    private function pointEditor(object $order, ?object $snapshot): ?array
    {
        $delivery = $this->entityFactory->get(DeliveriesEntity::class)->get((int)($order->delivery_id ?? 0));
        if (!$delivery || (int)$delivery->module_id !== (int)$this->module->getModuleIdByNamespace(__CLASS__)) {
            return null;
        }
        $settings = is_string($delivery->settings ?? null) ? (@unserialize($delivery->settings, ['allowed_classes' => false]) ?: []) : (array)($delivery->settings ?? []);
        $mode = DeliveryMode::fromServiceType($settings['service_type'] ?? null);
        $state = OrderPointChange::editorState($snapshot, $mode->value, $this->waybillRows->forOrder((int)$order->id), time());

        return $state + [
            'orderId' => (int)$order->id,
            'mode' => $mode->value,
            'settlementId' => $snapshot && $snapshot->mode === $mode->value ? (int)$snapshot->settlement_id : null,
            'settlementName' => $snapshot && $snapshot->mode === $mode->value ? (string)$snapshot->settlement_name : null,
            'showLifting' => $this->shipping->read()->showLifting,
            'currency' => (string)($this->entityFactory->get(CurrenciesEntity::class)->getMainCurrency()->sign ?? ''),
            'textsJson' => json_encode(array_combine(self::EDITOR_TEXTS, array_map(fn(string $key): string => (string)$this->translations->getTranslation('sviat__novaposhta__' . $key), self::EDITOR_TEXTS)), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ];
    }

    /**
     * ChainExtension of BackendOrdersHelper::buildFilter: NP status group, a keyword that is a waybill
     * number, and the list action «create waybills» (the core runs its own actions just before this).
     */
    public function listFilter($filter)
    {
        if (!is_array($filter)) {
            return $filter;
        }
        $this->handleMassAction();
        $group = $this->request->get('np_waybill');
        $group = is_string($group) && isset(WaybillStatus::FILTER_GROUPS[$group]) ? $group : null;
        $this->design->assign('np_status_groups', array_keys(WaybillStatus::FILTER_GROUPS));
        $this->design->assign('np_status_group', $group);
        $keyword = $this->request->get('keyword');
        $applied = OrdersListFilter::apply(
            $filter,
            $group,
            is_string($keyword) ? $keyword : null,
            fn(string $number): array => $this->waybillRows->ordersOfNumber($number),
            fn(array $codes): array => $this->waybillRows->ordersWithStatus($codes),
        );
        // The core shows the search text only while it stays in the filter; a waybill number was taken out.
        if (isset($filter['keyword']) && !isset($applied['keyword'])) {
            $this->design->assign('keyword', $filter['keyword']);
        }

        return $applied;
    }

    private function handleMassAction(): void
    {
        if ($this->request->method('post')) {
            if ($this->massAction->handleRequest(fn(): bool => (bool)$this->request->checkSession(), $_SESSION)) {
                Response::redirectTo($this->request->url());
            }

            return;
        }
        $report = OrdersMassAction::take($_SESSION);
        $this->design->assign('np_mass_report', $report === null ? null : OrdersMassAction::view(
            $report,
            fn(string $key): string => (string)$this->translations->getTranslation('sviat__novaposhta__' . $key),
        ));
    }

    /** ChainExtension of BackendOrdersHelper::buildCountStatusesFilter: the status counters follow the NP filter. */
    public function countFilter($countFilter, $filter = [])
    {
        if (is_array($countFilter) && is_array($filter)) {
            foreach (['id', 'np_without_waybill'] as $key) {
                if (isset($filter[$key])) {
                    $countFilter[$key] = $filter[$key];
                }
            }
            if (isset($filter['id']) && !isset($filter['keyword'])) {
                unset($countFilter['keyword']);
            }
        }

        return $countFilter;
    }

    /** QueueExtension of BackendOrdersHelper::findOrders: one query for the waybills of the page. */
    public function listOrders($orders, $filter = [])
    {
        if (!is_array($orders) || $orders === []) {
            return;
        }
        $rows = $this->waybillRows->forOrders(array_map(static fn($o): int => (int)$o->id, array_values($orders)));
        foreach ($orders as $order) {
            $row = $rows[(int)$order->id] ?? null;
            if ($row === null || PendingWaybill::is($row)) {
                continue;
            }
            $code = isset($row->status_code) ? (int)$row->status_code : null;
            $number = (string)$row->number;
            $order->np_waybill = [
                'numberSpaced' => strlen($number) === 14 ? substr($number, 0, 2) . ' ' . implode(' ', str_split(substr($number, 2), 4)) : $number,
                'group' => WaybillStatus::group($code),
                'statusKey' => $code ? 'sviat__novaposhta__' . WaybillStatus::textKey($code) : null,
                'statusText' => (string)($row->status_text ?? ''),
            ];
        }
    }

    /** ChainExtension of BackendProductsRequest::postVariants: the volume column is decimal, text must not reach it. */
    public function cleanVariantVolumes($variants)
    {
        if (!is_array($variants)) {
            return $variants;
        }
        foreach ($variants as $variant) {
            if (is_object($variant) && property_exists($variant, VariantVolume::FIELD)) {
                $variant->{VariantVolume::FIELD} = VariantVolume::clean($variant->{VariantVolume::FIELD});
            }
        }

        return $variants;
    }
}
