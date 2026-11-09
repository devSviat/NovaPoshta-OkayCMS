<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/**
 * Створює накладні для вибраних у списку замовлень, одну за одною в цьому запиті, кожну
 * так само, як форма зі своїми значеннями за замовчуванням.
 */
final class MassWaybills
{
    /** Кожне замовлення — один або кілька викликів Нової Пошти до 30 с; сторінка чекає на всі. */
    public const int LIMIT = 20;
    /**
     * Після цього нове замовлення не починається. nginx за замовчуванням дає PHP 60 с, а останньому
     * розпочатому замовленню ще потрібні власні кілька секунд; повільніша НП лишає решту на наступний запуск.
     */
    public const float BUDGET_SECONDS = 45.0;

    private readonly \Closure $clock;

    /** @param ?\Closure(): float $clock секунди */
    public function __construct(
        private readonly WaybillService $service,
        private readonly WaybillRows $rows,
        private readonly MassOrders $orders,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): float => microtime(true);
    }

    /** @param array<mixed> $rawIds надісланий `check[]` */
    public function run(array $rawIds): MassReport
    {
        $ids = self::ids($rawIds);
        $batch = array_slice($ids, 0, self::LIMIT);
        $untouched = count($ids) - count($batch);
        $start = ($this->clock)();
        $rows = [];
        foreach ($batch as $index => $orderId) {
            if (($this->clock)() - $start > self::BUDGET_SECONDS) {
                $untouched += count($batch) - $index;
                break;
            }
            try {
                $rows[] = ['orderId' => $orderId] + $this->one($orderId);
            } catch (\Throwable) {
                // Одне зламане замовлення не має позбавити звіту решту.
                $rows[] = ['orderId' => $orderId, 'kind' => 'failed', 'key' => 'waybill_advice_generic', 'value' => null];
            }
        }

        return new MassReport($rows, $untouched);
    }

    /** @return array{kind: 'created'|'skipped'|'failed', key: ?string, value: ?string} */
    private function one(int $orderId): array
    {
        $skip = static fn(string $key, ?string $value = null): array => ['kind' => 'skipped', 'key' => $key, 'value' => $value];
        if (!$this->orders->isOurs($orderId)) {
            return $skip('mass_not_np');
        }
        $existing = $this->rows->forOrder($orderId);
        // Резервацію оцінює create(): відмова, поки свіжа, а коли застаріла — доки менеджер не перевірить НП.
        if ($existing !== null && !PendingWaybill::is($existing)) {
            return $skip('mass_has_waybill', (string)$existing->number);
        }
        $draft = $this->orders->draft($orderId);
        if (is_string($draft)) {
            return $skip($draft);
        }
        $issue = $draft->organizationIssue();
        if ($issue !== null) {
            return $skip($issue);
        }
        if ($draft->chargesDeliveryTwice()) {
            return $skip('mass_double_delivery');
        }
        if ($draft->awaitsPayment) {
            return $skip('mass_unpaid');
        }

        $outcome = $this->service->create($orderId, $draft);
        if ($outcome->ok) {
            return ['kind' => 'created', 'key' => null, 'value' => $outcome->number];
        }
        $first = $outcome->issues[0] ?? ['advice' => 'waybill_advice_generic', 'message' => null];
        if (in_array($first['advice'], ['waybill_pending', 'waybill_uncertain_check'], true)) {
            return $skip($first['advice']);
        }

        return ['kind' => 'failed', 'key' => $first['advice'], 'value' => $first['message']];
    }

    /** @return list<int> додатні, унікальні, у надісланому порядку */
    private static function ids(array $raw): array
    {
        $ids = [];
        foreach ($raw as $value) {
            if ((is_string($value) || is_int($value)) && preg_match('/^\d{1,10}$/', (string)$value) && (int)$value > 0) {
                $ids[(int)$value] = true;
            }
        }

        return array_keys($ids);
    }
}
