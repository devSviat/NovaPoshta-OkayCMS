<?php

namespace Okay\Modules\Sviat\NovaPoshta\Extenders;

use Okay\Core\BackendTranslations;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Modules\Modules;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrdersListFilter;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillRows;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStatus;

/** Ланки на DeliveryFilterHelper модуля Sviat/DeliveryFilter; Init реєструє їх лише тоді, коли хаб є серед файлів. */
class DeliveryFilterExtender implements ExtensionInterface
{
    public const string CARRIER = 'np';

    public function __construct(
        private readonly WaybillRows $waybillRows,
        private readonly BackendTranslations $translations,
        private readonly Modules $modules,
    ) {
    }

    /** ChainExtension для findDeliveryStatusFilters. `np_waybill` — параметр старих закладок, хаб переводить їх на свій. */
    public function deliveryStatusFilters($filters)
    {
        if (!is_array($filters) || !$this->enabled()) {
            return $filters;
        }
        $options = [];
        foreach (array_keys(WaybillStatus::FILTER_GROUPS) as $group) {
            $options[$group] = $this->text('status_group_' . $group);
        }
        $filters[self::CARRIER] = [
            'title' => $this->text('delivery_filter_title'),
            'short' => $this->text('delivery_filter_short'),
            'options' => $options,
            'legacy_param' => 'np_waybill',
        ];

        return $filters;
    }

    /** ChainExtension для filterByDeliveryStatus: той самий фільтр сутності, що й старий параметр `np_waybill`. */
    public function filterByDeliveryStatus($result, $filter = [], $carrier = '', $status = '')
    {
        if (!is_array($result) || $carrier !== self::CARRIER || !$this->enabled() || !is_string($status)) {
            return $result;
        }

        return OrdersListFilter::apply($result, $status, null, static fn(): array => []);
    }

    /** ChainExtension для findOrderIdsByShipmentNumber: замовлення з цією ТТН. */
    public function ordersByShipmentNumber($ids, $keyword = '')
    {
        $number = OrdersListFilter::number(is_string($keyword) ? $keyword : null);
        if (!is_array($ids) || $number === null || !$this->enabled()) {
            return $ids;
        }

        return array_merge($ids, $this->waybillRows->ordersOfNumber($number));
    }

    /** Адмінка запускає init() і вимкнених модулів, тож ланки вимкненого перевізника зареєстровані й мусять мовчати. */
    private function enabled(): bool
    {
        return (bool)$this->modules->isActiveModule('Sviat', 'NovaPoshta');
    }

    private function text(string $key): string
    {
        return (string)$this->translations->getTranslation('sviat__novaposhta__' . $key);
    }
}
