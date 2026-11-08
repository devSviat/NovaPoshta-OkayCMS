<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** What of a getStatusDocuments answer is kept with the waybill. */
final class WaybillTrackingDocument
{
    /**
     * The new answer, stamped with when it was fetched. A recipient phone NP hid this time (asked
     * with another phone) is kept from the previous answer: refresh needs it to see the full data.
     */
    public static function merge(array $document, ?array $previous, string $now): array
    {
        if (trim((string)($document['PhoneRecipient'] ?? '')) === '' && is_array($previous) && trim((string)($previous['PhoneRecipient'] ?? '')) !== '') {
            $document['PhoneRecipient'] = $previous['PhoneRecipient'];
        }
        $document['_fetched_at'] = $now;

        return $document;
    }

    /**
     * The status worth keeping. "Number not found" (3) right after creation only means NP has not
     * seen the waybill yet: storing it would stop tracking; only the tracker's 30-day rule sets it.
     */
    public static function status(array $document): ?int
    {
        $code = (int)($document['StatusCode'] ?? 0);

        return $code > 0 && $code !== WaybillStatus::NOT_FOUND ? $code : null;
    }

    /**
     * Status columns to write from a getStatusDocuments answer; empty when it has no status worth keeping.
     * A changed status takes the date of the scan that produced it (NP: DateScan, "H:i d.m.Y" in Kyiv time),
     * or none: the previous status's date would date it wrong. An unchanged status keeps the tracker's date.
     *
     * @return array{status_code?: int, status_text?: string, status_at?: ?string}
     */
    public static function statusColumns(array $document, ?object $row): array
    {
        $code = self::status($document);
        if ($code === null) {
            return [];
        }
        $columns = ['status_code' => $code, 'status_text' => mb_substr((string)($document['Status'] ?? ''), 0, 255)];
        if ($row === null || (int)($row->status_code ?? 0) !== $code) {
            $columns['status_at'] = self::scanDate($document['DateScan'] ?? null);
        }

        return $columns;
    }

    /** NP's DateScan ("H:i d.m.Y", Kyiv time) — the date of the scan that produced the status — in the shop's time. */
    public static function scanDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!H:i d.m.Y', trim($value), new \DateTimeZone('Europe/Kyiv'));

        return $date === false ? null : $date->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }
}
