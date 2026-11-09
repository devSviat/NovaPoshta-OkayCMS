<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Storage\StateStorage;

/**
 * Statuses of waybills. The scheduler asks the new API about unfinished waybills in
 * batches; the card's full data comes from the old getStatusDocuments, which the new API lacks.
 */
final class WaybillTracking
{
    /** The new API takes at most 100 numbers per request (101 → 422, measured 05.11.2026). */
    private const int BATCH = 100;
    private const int BATCHES_PER_RUN = 10;
    private const string RUNNING = 'tracking_running';
    /** Stock 4.5.3 ignores the scheduler lock; a run older than this died and is not waited for. */
    private const int RUNNING_FOR = 600;
    private const int NOT_FOUND_AFTER_DAYS = 30;

    /** A parcel still moving after two months is stuck (never handed over, endless return); asking again only slows the queue. */
    private const int TRACK_FOR_DAYS = 60;
    private const int TIMEOUT_SECONDS = 30;

    private readonly \Closure $now;

    /** @param ?\Closure(): int $now */
    public function __construct(
        private readonly NewApiClient $newApi,
        private readonly OldApiClient $oldApi,
        private readonly WaybillRows $rows,
        private readonly StateStorage $state,
        ?\Closure $now = null,
    ) {
        $this->now = $now ?? time(...);
    }

    /** @return array{checked: int, updated: int, requests: int} */
    public function tick(): array
    {
        $result = ['checked' => 0, 'updated' => 0, 'requests' => 0];
        $now = ($this->now)();
        $running = (int)$this->state->get(self::RUNNING);
        if ($running > 0 && $now - $running < self::RUNNING_FOR) {
            return $result;
        }
        $this->state->set(self::RUNNING, (string)$now);
        try {
            // Taken once: rows checked during the run move to the end of the queue.
            $due = $this->rows->due(self::BATCH * self::BATCHES_PER_RUN, 1, date('Y-m-d H:i:s', $now - self::TRACK_FOR_DAYS * 86400));
            foreach (array_chunk($due, self::BATCH) as $batch) {
                $result['requests']++;
                $byNumber = [];
                foreach ($batch as $row) {
                    $byNumber[(string)$row->number] = $row;
                }
                try {
                    $answer = $this->newApi->quick(self::TIMEOUT_SECONDS)->get('/shipments/tracking?' . implode('&', array_map(static fn(string $n): string => 'numbers%5B%5D=' . rawurlencode($n), array_keys($byNumber))));
                } catch (NpApiException) {
                    // Nothing is written: the next run asks again.
                    break;
                }
                foreach ((array)($answer['items'] ?? []) as $item) {
                    $status = is_array($item['currentStatus'] ?? null) ? $item['currentStatus'] : [];
                    $row = $byNumber[(string)($status['number'] ?? '')] ?? null;
                    if ($row === null || !is_numeric($status['statusCode'] ?? null)) {
                        continue;
                    }
                    if ((int)$status['statusCode'] === WaybillStatus::NOT_FOUND) {
                        // Not known yet; only the 30-day rule below gives up on it.
                        continue;
                    }
                    $this->rows->saveStatus((int)$row->order_id, (int)$status['statusCode'], (string)($status['status'] ?? ''), self::date($status['statusDate'] ?? null), self::date($status['scheduledDate'] ?? null));
                    unset($byNumber[(string)$row->number]);
                    $result['updated']++;
                }
                foreach ($byNumber as $row) {
                    $age = $now - (int)strtotime((string)($row->created_at ?? ''));
                    if ($age > self::NOT_FOUND_AFTER_DAYS * 86400) {
                        $this->rows->saveStatus((int)$row->order_id, WaybillStatus::NOT_FOUND, '', null, null);
                    } else {
                        $this->rows->markChecked((int)$row->order_id);
                    }
                }
                $result['checked'] += count($batch);
            }
        } finally {
            $this->state->set(self::RUNNING, null);
        }

        return $result;
    }

    /**
     * The full document for the card; without the recipient's phone NP hides part of it. The phone
     * the waybill was made for wins over the order's: the manager may have changed it in the form.
     */
    public function refresh(int $orderId, ?string $phone): WaybillOutcome
    {
        $row = $this->rows->forOrder($orderId);
        if ($row === null || PendingWaybill::is($row)) {
            return WaybillOutcome::refused('waybill_none');
        }
        $known = json_decode((string)($row->tracking ?? ''), true);
        $phone = NpPhone::normalize((string)($known['PhoneRecipient'] ?? '')) ?? $phone;
        try {
            $document = $this->oldApi->quick(self::TIMEOUT_SECONDS)->call('TrackingDocument', 'getStatusDocuments', ['Documents' => [['DocumentNumber' => (string)$row->number, 'Phone' => (string)$phone]]])[0] ?? null;
        } catch (NpApiException $e) {
            return WaybillOutcome::failed(WaybillIssues::explain($e));
        }
        if (!is_array($document)) {
            return WaybillOutcome::refused('waybill_not_found');
        }
        $this->rows->saveTracking($orderId, $document);

        return new WaybillOutcome(true, (string)$row->number, [], $phone === null ? ['waybill_tracking_no_phone'] : []);
    }

    /** NP's ISO time in the shop's time zone; its "0001-01-01" means none. */
    private static function date(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || str_starts_with($value, '0001-')) {
            return null;
        }
        try {
            return new \DateTimeImmutable($value)->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }
}
