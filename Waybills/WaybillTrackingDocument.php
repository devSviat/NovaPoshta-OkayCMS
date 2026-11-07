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
}
