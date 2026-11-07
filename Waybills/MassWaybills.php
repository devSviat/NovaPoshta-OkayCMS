<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/**
 * Creates waybills for the orders selected in the list, one after another in this request, each
 * exactly as the form would with its defaults.
 */
final class MassWaybills
{
    /** Each order is one or more Nova Poshta calls of up to 30 s; the page waits for all of them. */
    public const int LIMIT = 20;
    /** No new order is started after this: the request must end before PHP or the proxy gives up. */
    public const float BUDGET_SECONDS = 100.0;

    private readonly \Closure $clock;

    /** @param ?\Closure(): float $clock seconds */
    public function __construct(
        private readonly WaybillService $service,
        private readonly WaybillRows $rows,
        private readonly MassOrders $orders,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): float => microtime(true);
    }

    /** @param array<mixed> $rawIds the posted `check[]` */
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
            $rows[] = ['orderId' => $orderId] + $this->one($orderId);
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
        if ($existing !== null && $existing->api === 'pending') {
            if (WaybillService::isPending($existing, time())) {
                return $skip('waybill_pending');
            }
        } elseif ($existing !== null) {
            return $skip('waybill_already', (string)$existing->number);
        }
        $draft = $this->orders->draft($orderId);
        if (is_string($draft)) {
            return $skip($draft);
        }
        $issue = $draft->organizationIssue();
        if ($issue !== null) {
            return $skip($issue);
        }

        $outcome = $this->service->create($orderId, $draft);
        if ($outcome->ok) {
            return ['kind' => 'created', 'key' => null, 'value' => $outcome->number];
        }
        $first = $outcome->issues[0] ?? ['advice' => 'waybill_advice_generic', 'message' => null];

        return ['kind' => 'failed', 'key' => $first['advice'], 'value' => $first['message']];
    }

    /** @return list<int> positive, unique, in the posted order */
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
