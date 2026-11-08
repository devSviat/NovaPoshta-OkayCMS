<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy\Steps;

use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyBudget;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyData;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyOrders;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReport;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyStep;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillNumber;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillTrackingDocument;

/**
 * NovaPoshtaTracking's waybills. Its tracking_response is one getStatusDocuments item, the format the
 * module keeps too, so the card shows the history without asking Nova Poshta.
 */
final class WaybillsStep implements LegacyStep
{
    public const string NAME = 'waybills';
    private const int BATCH = 200;
    private const int DELETED = 2;

    public function __construct(private readonly LegacyData $old, private readonly LegacyOrders $orders)
    {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(int $cursor, LegacyBudget $budget, LegacyReport $report): int
    {
        $rows = $this->old->trackingRows($cursor, self::BATCH);
        foreach ($rows as $row) {
            if (!$budget->hasTime()) {
                return $cursor;
            }
            $this->one($row, $report);
            $cursor = (int)$row->id;
        }

        return count($rows) < self::BATCH ? self::DONE : $cursor;
    }

    private function one(object $row, LegacyReport $report): void
    {
        $orderId = (int)($row->order_id ?? 0);
        if ($orderId <= 0 || $this->orders->order($orderId) === null) {
            $report->fail(self::NAME, $orderId > 0 ? $orderId : null, 'legacy_fail_no_order');

            return;
        }
        if ($this->orders->hasWaybill($orderId)) {
            $report->add(self::NAME, 'existed');

            return;
        }
        $number = WaybillNumber::normalize((string)($row->int_doc_number ?? ''));
        if ($number === null) {
            $report->fail(self::NAME, $orderId, 'legacy_fail_bad_number', mb_substr((string)($row->int_doc_number ?? ''), 0, 36));

            return;
        }
        if ($this->orders->waybillOwner($number) !== null) {
            $report->fail(self::NAME, $orderId, 'legacy_fail_number_taken', $number);

            return;
        }
        $document = json_decode((string)($row->tracking_response ?? ''), true);
        $document = is_array($document) ? $document : null;
        if ((int)($row->status_code ?? 0) === self::DELETED || (int)($document['StatusCode'] ?? 0) === self::DELETED) {
            $report->fail(self::NAME, $orderId, 'legacy_fail_deleted', $number);

            return;
        }
        $fetchedAt = self::date($row->tracking_update_at ?? null);
        $ref = trim((string)($row->ref_id ?? '')) ?: (is_string($document['Ref'] ?? null) && $document['Ref'] !== '' ? $document['Ref'] : null);
        $code = $document !== null ? WaybillTrackingDocument::status($document) : null;
        $values = [
            'number' => $number,
            'ref' => $ref,
            // A Ref means the shop created it: deleting then goes through NP, as for the module's own.
            'api' => $ref !== null ? 'old' : 'attached',
            'status_code' => $code,
            'status_text' => $code !== null ? mb_substr(trim((string)($document['Status'] ?? '')), 0, 255) : null,
            // The date of the status at NP, as the tracker writes it; the old module's refresh time only without one.
            'status_at' => $code !== null ? (WaybillTrackingDocument::scanDate($document['DateScan'] ?? null) ?? $fetchedAt) : null,
            'scheduled_at' => self::date($document['ScheduledDeliveryDate'] ?? null),
            'tracking' => $document !== null ? WaybillTrackingDocument::merge($document, null, $fetchedAt ?? date('Y-m-d H:i:s')) : null,
            'tracked_at' => $fetchedAt,
        ];
        $report->add(self::NAME, $this->orders->importWaybill($orderId, $values) ? 'done' : 'existed');
    }

    private static function date(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '' || str_starts_with($value, '0000-') || str_starts_with($value, '0001-')) {
            return null;
        }
        try {
            return new \DateTimeImmutable(trim($value))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }
}
