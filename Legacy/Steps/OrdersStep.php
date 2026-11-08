<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy\Steps;

use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyAddressMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyBudget;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyCityMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyData;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyDivisionMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyNames;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyOrders;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyPlaces;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReport;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyStep;

/** NovaposhtaCost's point or address of each order, matched to our dictionary or kept by name. */
final class OrdersStep implements LegacyStep
{
    public const string NAME = 'orders';
    private const int BATCH = 200;
    private const string WAIT = 'wait';

    public function __construct(
        private readonly LegacyData $old,
        private readonly LegacyOrders $orders,
        private readonly LegacyPlaces $places,
        private readonly LegacyCityMatcher $cities,
        private readonly LegacyDivisionMatcher $divisions,
        private readonly LegacyAddressMatcher $addresses,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(int $cursor, LegacyBudget $budget, LegacyReport $report): int
    {
        $rows = $this->old->deliveryRows($cursor, self::BATCH);
        foreach ($rows as $row) {
            if (!$budget->hasTime() || $this->one($row, $budget, $report) === self::WAIT) {
                return $cursor;
            }
            $cursor = (int)$row->id;
        }

        return count($rows) < self::BATCH ? self::DONE : $cursor;
    }

    private function one(object $row, LegacyBudget $budget, LegacyReport $report): ?string
    {
        $orderId = (int)($row->order_id ?? 0);
        $order = $orderId > 0 ? $this->orders->order($orderId) : null;
        if ($order === null) {
            // No such order in the shop: the old record is named, a link would lead nowhere.
            $report->fail(self::NAME, null, $orderId > 0 ? 'legacy_fail_no_order' : 'legacy_fail_no_order_id', $orderId > 0 ? (string)$orderId : null, (int)($row->id ?? 0) ?: null);

            return null;
        }
        if ($this->orders->hasDelivery($orderId)) {
            $report->add(self::NAME, 'existed');

            return null;
        }
        $warehouseRef = trim((string)($row->warehouse_id ?? ''));
        if ($warehouseRef !== '') {
            $values = $this->point($row, $warehouseRef, $budget);
        } elseif (trim((string)($row->street ?? '')) !== '') {
            $values = $this->address($row);
        } else {
            $report->fail(self::NAME, $orderId, 'legacy_fail_no_data');

            return null;
        }
        if ($values === null) {
            return self::WAIT;
        }
        [$values, $problem, $name] = $values;
        $values += [
            'cod' => (int)($row->redelivery ?? 0) === 1 ? 1 : 0,
            'delivery_price' => (float)($order->delivery_price ?? 0),
            'price_source' => 'legacy',
            'legacy' => $problem === null ? 'migrated' : 'unmatched',
        ];
        if (!$this->orders->importDelivery($orderId, (int)$order->delivery_id, $values)) {
            $report->add(self::NAME, 'existed');
        } elseif ($problem !== null) {
            $report->fail(self::NAME, $orderId, $problem, $name);
        } else {
            $report->add(self::NAME, 'done');
        }

        return null;
    }

    /** @return array{0: array, 1: ?string, 2: ?string}|null values, problem key, name for the report; null — wait for NP */
    private function point(object $row, string $warehouseRef, LegacyBudget $budget): ?array
    {
        $warehouse = $this->old->warehouse($warehouseRef);
        $cityRef = (string)($warehouse->city_ref ?? $row->city_id ?? '');
        $cityName = $cityRef !== '' ? $this->old->cityName($cityRef) : null;
        [$settlementId, $why] = $this->cities->match($cityRef, $cityName, $budget);
        if ($why === LegacyCityMatcher::BUDGET) {
            return null;
        }
        $oldName = trim((string)($warehouse->name ?? ''));
        $division = $settlementId !== null && $warehouse !== null
            ? $this->divisions->match($settlementId, LegacyNames::number($warehouse->number ?? null, $oldName), $oldName)
            : null;
        $values = ['mode' => DeliveryMode::Points->value] + $this->settlementValues($settlementId, $cityName, null, null);
        if ($division !== null) {
            [$title, $place] = array_pad(explode(': ', (string)$division->name_uk, 2), 2, null);

            return [$values + [
                'division_id' => (int)$division->id,
                'division_number' => (string)$division->number,
                'division_category' => (string)$division->category,
                'division_name' => $title,
                'division_address' => $place ?? (($division->address_uk ?? null) ?: null),
            ], null, null];
        }

        return [
            $values + ['division_id' => null, 'division_name' => $oldName !== '' ? $oldName : null],
            $settlementId !== null ? 'legacy_fail_no_point' : ($why ?? 'legacy_fail_no_place'),
            $oldName !== '' ? $oldName : $cityName,
        ];
    }

    /** @return array{0: array, 1: ?string, 2: ?string} */
    private function address(object $row): array
    {
        $city = trim((string)($row->city_name ?? ''));
        $area = trim((string)($row->area_name ?? '')) ?: null;
        $district = trim((string)($row->region_name ?? '')) ?: null;
        [$settlementId, $why] = $this->addresses->match($city, $area, $district);
        $floor = (int)($row->lifting_floor ?? 0);
        $lifting = (int)($row->door_delivery ?? 0) === 1 && $floor >= 1 && $floor <= 99;

        return [
            ['mode' => DeliveryMode::Address->value] + $this->settlementValues($settlementId, $city, $area, $district) + [
                'street_name' => trim((string)$row->street),
                'house' => trim((string)($row->house ?? '')) ?: null,
                'flat' => trim((string)($row->apartment ?? '')) ?: null,
                'address_note' => trim((string)($row->recipient_address_note ?? '')) ?: null,
                'lifting' => $lifting ? 1 : 0,
                'floor' => $lifting ? $floor : null,
                'elevator' => 0,
            ],
            $why,
            $city !== '' ? $city : null,
        ];
    }

    private function settlementValues(?int $settlementId, ?string $name, ?string $area, ?string $district): array
    {
        $settlement = $settlementId !== null ? $this->places->settlement($settlementId) : null;
        if ($settlement === null) {
            return ['settlement_id' => null, 'settlement_name' => $name, 'area_name' => $area, 'district_name' => $district];
        }

        return [
            'settlement_id' => (int)$settlement->id,
            'settlement_name' => (string)$settlement->name_uk,
            'area_name' => ($settlement->area_uk ?? null) ?: null,
            'district_name' => ($settlement->district_uk ?? null) ?: null,
        ];
    }
}
