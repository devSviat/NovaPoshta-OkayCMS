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
    /**
     * No new order is started after this. nginx gives PHP 60 s by default, and the order started last
     * still needs its own few seconds; a slower NP leaves the rest for the next run.
     */
    public const float BUDGET_SECONDS = 45.0;

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
            try {
                $rows[] = ['orderId' => $orderId] + $this->one($orderId);
            } catch (\Throwable) {
                // One broken order must not cost the report of the others.
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
        // A reservation is judged by create(): fresh is refused, a dead one is replaced with a warning.
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
