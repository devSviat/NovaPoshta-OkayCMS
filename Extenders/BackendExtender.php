<?php

namespace Okay\Modules\Sviat\NovaPoshta\Extenders;

use Okay\Modules\Sviat\NovaPoshta\Backend\DeliveryWarnings;
use Okay\Modules\Sviat\NovaPoshta\Waybills\PendingWaybill;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryStore;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderRowsCleanup;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryLines;
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
        private readonly OrderRowsCleanup $orderRows,
        private readonly OrderDeliveryLines $orderLines,
    ) {
    }

    /** ChainExtension для BackendPaymentsRequest::postPayment: ядро зберігає поле разом з рештою даних оплати. */
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

    /** QueueExtension для BackendPaymentsHelper::getPaymentMethod: те, що показує payment_cod.tpl. */
    public function assignPaymentCod($payment, $id = null)
    {
        $this->design->assign('np_codpay', [
            'kind' => is_object($payment) ? $this->codPayments->kindOfPayment($payment)?->value : null,
            'manual' => CodPayments::isManual(is_object($payment) ? ($payment->module ?? null) : null),
        ]);
    }

    /**
     * ChainExtension для BackendDeliveriesHelper::getDelivery: режим, збережений NovaposhtaCost або старішою версією,
     * лишається вибраним.
     */
    public function offeredMode($delivery, $id = null)
    {
        if (is_object($delivery) && !empty($delivery->module_id) && is_array($delivery->delivery_settings ?? null)
            && (int)$delivery->module_id === (int)$this->module->getModuleIdByNamespace(__CLASS__)) {
            $delivery->delivery_settings = DeliveryMode::offered($delivery->delivery_settings);
        }

        return $delivery;
    }

    /**
     * Queue extender для BackendDeliveriesHelper::getDelivery: сторінка способу доставки попереджає, коли ядро робить
     * його безкоштовним для будь-якого кошика.
     */
    public function deliveryWarning($delivery, $id = null): void
    {
        $ours = is_object($delivery) && DeliveryWarnings::freeForAll([$delivery], (int)$this->module->getModuleIdByNamespace(__CLASS__)) !== [];
        $this->design->assign('np_delivery_free_for_all', $ours);
    }

    /** Queue extender для BackendOrdersHelper::delete (виконується до того, як ядро видалить замовлення). */
    public function deleteOrders($result, $ids): void
    {
        $this->orderRows->forOrders((array)$ids);
    }

    /** QueueExtension для BackendOrdersHelper::findOrder: сторінка замовлення показує, куди йде посилка. */
    public function assignOrderDelivery($order, $orderId = null)
    {
        $found = is_object($order) && !empty($order->id);
        $delivery = $found ? $this->orderLines->ownDelivery($order) : null;
        $row = $found ? $this->orders->forOrder((int)$order->id) : null;
        $point = $delivery ? $this->pointEditor($order, $delivery, $row) : null;
        $lines = null;
        if ($point === null) {
            // Доставку змінено на спосіб іншого модуля: старий пункт не показується, а накладна, створена до зміни,
            // лишається видимою — її треба видалити чи відв'язати; форми нової накладної до старого пункту немає.
            $waybill = $row ? $this->waybillRows->forOrder((int)$order->id) : null;
            $showWaybill = $waybill !== null && !PendingWaybill::is($waybill);
        } else {
            $showWaybill = $point['showWaybill'];
            // needsChoice: збережений пункт видачі належить іншому режиму (спосіб доставки змінився) або його ще немає.
            if ($row && !$point['needsChoice']) {
                $lines = $this->orderLines->lines($order, $delivery, $row);
            }
        }
        $this->design->assign('np_order_point', $point);
        $this->design->assign('np_order_delivery', $lines);
        $this->design->assign('np_waybill', $showWaybill ? $this->waybills->forOrder((int)$order->id) : null);
    }

    /** Тексти, які показує скрипт редактора пункту видачі/адреси; ключі Backend/lang без префікса. */
    private const array EDITOR_TEXTS = [
        'op_city', 'op_city_placeholder', 'op_point_search', 'op_point_search_placeholder', 'op_street', 'op_house', 'op_flat', 'op_entrance',
        'op_note', 'op_lifting', 'op_floor', 'op_elevator', 'op_save', 'op_cancel', 'op_saving', 'op_no_points', 'op_shown_of', 'op_all_day',
        'op_weekdays', 'op_day_off', 'op_unfit_too_heavy', 'op_unfit_too_big', 'op_unfit_too_expensive', 'op_choose_point', 'op_network',
        'op_price_keep', 'op_price_same', 'op_price_free', 'op_price_show', 'op_price_auto', 'op_price_offer', 'op_price_apply', 'op_price_applied', 'op_street_typed', 'op_change',
    ];

    /** Те, що потрібно редактору пункту видачі/адреси; $delivery — спосіб цього модуля (OrderDeliveryLines::ownDelivery). */
    private function pointEditor(object $order, object $delivery, ?object $snapshot): array
    {
        $settings = is_string($delivery->settings ?? null) ? (@unserialize($delivery->settings, ['allowed_classes' => false]) ?: []) : (array)($delivery->settings ?? []);
        $mode = DeliveryMode::fromServiceType($settings['service_type'] ?? null);
        $state = OrderPointChange::editorState($snapshot, $mode->value, $this->waybillRows->forOrder((int)$order->id), time());

        return $state + [
            'orderId' => (int)$order->id,
            'mode' => $mode->value,
            'settlementId' => $snapshot && $snapshot->mode === $mode->value ? (int)$snapshot->settlement_id : null,
            'settlementName' => $snapshot && $snapshot->mode === $mode->value ? (string)$snapshot->settlement_name : null,
            // Показується рядком, а не полем для редагування: тип перед жирною назвою, область нижче.
            'settlementType' => $snapshot && $snapshot->mode === $mode->value ? \Okay\Modules\Sviat\NovaPoshta\Cart\SettlementName::split((string)$snapshot->settlement_name)[0] : null,
            'settlementTitle' => $snapshot && $snapshot->mode === $mode->value ? \Okay\Modules\Sviat\NovaPoshta\Cart\SettlementName::split((string)$snapshot->settlement_name)[1] : null,
            'settlementArea' => $snapshot && $snapshot->mode === $mode->value ? (string)($snapshot->area_name ?? '') : null,
            'settlementDistrict' => $snapshot && $snapshot->mode === $mode->value ? (string)($snapshot->district_name ?? '') : null,
            'showLifting' => $this->shipping->read()->showLifting,
            'currency' => (string)($this->entityFactory->get(CurrenciesEntity::class)->getMainCurrency()->sign ?? ''),
            'textsJson' => json_encode(array_combine(self::EDITOR_TEXTS, array_map(fn(string $key): string => (string)$this->translations->getTranslation('sviat__novaposhta__' . $key), self::EDITOR_TEXTS)), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ];
    }

    /**
     * ChainExtension для BackendOrdersHelper::buildFilter: група статусів НП, ключове слово, що є номером
     * накладної, і дія списку «створити накладні» (ядро виконує свої дії безпосередньо перед цим).
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
        );
        // Ядро показує текст пошуку, лише поки він лишається у фільтрі; номер накладної звідти вилучено.
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

    /** ChainExtension для BackendOrdersHelper::buildCountStatusesFilter: лічильники статусів враховують фільтр НП. */
    public function countFilter($countFilter, $filter = [])
    {
        if (is_array($countFilter) && is_array($filter)) {
            foreach (['id', 'np_without_waybill', 'np_status_codes'] as $key) {
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

    /** QueueExtension для BackendOrdersHelper::findOrders: один запит на накладні всієї сторінки. */
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
                'number' => $number,
                'numberSpaced' => strlen($number) === 14 ? substr($number, 0, 2) . ' ' . implode(' ', str_split(substr($number, 2), 4)) : $number,
                'group' => WaybillStatus::group($code),
                'statusKey' => $code ? 'sviat__novaposhta__' . WaybillStatus::textKey($code) : null,
                'statusText' => (string)($row->status_text ?? ''),
            ];
        }
    }

    /**
     * ChainExtension для BackendProductsRequest::postVariants: колонка об'єму має тип decimal, текст не повинен туди
     * потрапити.
     */
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
