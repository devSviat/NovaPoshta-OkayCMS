<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;

/**
 * Creates, attaches and removes the waybill of an order. A create or delete is sent exactly once:
 * after a broken connection Nova Poshta may already have done it.
 */
final class WaybillService
{
    private const int TIMEOUT_SECONDS = 30;
    /** A reservation older than this belongs to a request that died (NP calls time out at 30 s). */
    private const int PENDING_STALE_SECONDS = 120;
    /** A new-API shipment shows up in the old API's document list only after a while; until then "none" proves nothing. */
    private const int LOOKUP_SETTLE_SECONDS = 600;
    private const string UNCERTAIN = 'uncertain';

    private readonly \Closure $today;
    private readonly \Closure $now;

    /**
     * @param ?\Closure(): string $today d.m.Y
     * @param ?\Closure(): int $now
     */
    public function __construct(
        private readonly OldApiClient $oldApi,
        private readonly NewApiClient $newApi,
        private readonly WaybillRows $rows,
        private readonly SenderSource $sender,
        private readonly RecipientCities $cities,
        ?\Closure $today = null,
        ?\Closure $now = null,
    ) {
        $this->today = $today ?? static fn(): string => date('d.m.Y');
        $this->now = $now ?? time(...);
    }

    /** A reservation of a request still running; a stale one is not. */
    public static function isPending(?object $row, int $now): bool
    {
        return PendingWaybill::is($row) && !self::needsCheck($row, $now);
    }

    /**
     * A reservation whose request may have made a waybill at NP: NP did not answer it, or the request
     * died midway. Nothing new is made for the order until lookup() has asked NP.
     */
    public static function needsCheck(?object $row, int $now): bool
    {
        return PendingWaybill::is($row)
            && (($row->audit_status ?? null) === self::UNCERTAIN || $now - (int)strtotime((string)($row->created_at ?? '')) >= self::PENDING_STALE_SECONDS);
    }

    public function create(int $orderId, WaybillDraft $draft): WaybillOutcome
    {
        $notices = [];
        $existing = $this->rows->forOrder($orderId);
        if (self::needsCheck($existing, ($this->now)())) {
            return WaybillOutcome::refused('waybill_uncertain_check');
        }
        if (self::isPending($existing, ($this->now)())) {
            return WaybillOutcome::refused('waybill_pending');
        }
        if ($existing !== null) {
            return WaybillOutcome::refused('waybill_already', (string)$existing->number);
        }
        $sender = $this->sender->party();
        if (is_string($sender)) {
            return WaybillOutcome::refused($sender);
        }

        $route = WaybillRoute::for($draft, ($this->today)());
        if ($route === 'new') {
            $request = fn(): array => $this->newApi->quick(self::TIMEOUT_SECONDS)->post('/shipments', NewShipmentRequest::build($draft, $sender));
        } else {
            if ($draft->mode === DeliveryMode::Address) {
                try {
                    [$properties, $notice] = $draft->recipientType === 'Organization'
                        ? $this->organizationAddressRequest($draft, $sender)
                        : $this->addressRequest($draft, $sender);
                } catch (NpApiException $e) {
                    return WaybillOutcome::failed(WaybillIssues::explain($e));
                }
                $notices = [...$notices, ...array_filter([$notice])];
            } else {
                $properties = OldDocumentRequest::forDivision($draft, $sender);
            }
            $guard = OldDocumentRequest::guard($properties);
            if ($guard !== null) {
                return WaybillOutcome::refused($guard);
            }
            $request = fn(): array => $this->oldApi->quick(self::TIMEOUT_SECONDS)->callWithWarnings('InternetDocumentGeneral', 'save', $properties);
        }

        if (!$this->rows->reserve($orderId)) {
            return WaybillOutcome::refused('waybill_pending');
        }
        try {
            $answer = $request();
        } catch (NpApiException $e) {
            if (self::uncertain($e)) {
                $this->rows->markUncertain($orderId);

                return WaybillOutcome::failed(WaybillIssues::explain('waybill_uncertain'));
            }
            // NP answered with a refusal: nothing was made.
            $this->rows->release($orderId);

            return WaybillOutcome::failed(WaybillIssues::explain($e));
        }

        if ($route === 'new') {
            $number = (string)($answer['number'] ?? '');
            $ref = (string)($answer['id'] ?? '');
        } else {
            $made = $answer['data'][0] ?? [];
            $number = (string)($made['IntDocNumber'] ?? '');
            $ref = (string)($made['Ref'] ?? '');
            $notices = [...$notices, ...WaybillIssues::notices($answer['warnings'])];
        }
        if (WaybillNumber::normalize($number) === null) {
            $this->rows->markUncertain($orderId);

            return WaybillOutcome::refused('waybill_uncertain');
        }

        $saved = $this->rows->complete($orderId, $number, $ref !== '' ? $ref : null, $route, [
            'mode' => $draft->mode->value,
            'cod' => $draft->cod,
            'codAmount' => $draft->codAmount,
            'codKind' => $draft->codKind->value,
            'codPayer' => $draft->codPayer,
            'lifting' => $draft->lifting,
            'floor' => $draft->floor,
        ]);
        if (!$saved) {
            $this->rows->release($orderId);

            return WaybillOutcome::refused('waybill_not_saved', $number);
        }
        $status = $this->status($number, $draft->recipientPhone);
        $audit = WaybillAudit::compare($draft, $status ?? []);
        $this->rows->saveAudit($orderId, $audit);
        if ($status !== null) {
            // The same answer fills the card: no second request.
            $this->rows->saveTracking($orderId, $status);
        }

        return new WaybillOutcome(true, $number, [], array_values(array_unique($notices)), $audit);
    }

    public function attach(int $orderId, string $input, ?string $phone): WaybillOutcome
    {
        $number = WaybillNumber::normalize($input);
        if ($number === null) {
            return WaybillOutcome::refused('waybill_bad_number');
        }
        $existing = $this->rows->forOrder($orderId);
        if (self::isPending($existing, ($this->now)())) {
            return WaybillOutcome::refused('waybill_pending');
        }
        if (PendingWaybill::is($existing)) {
            $this->rows->release($orderId);
        } elseif ($existing !== null) {
            return WaybillOutcome::refused('waybill_already', (string)$existing->number);
        }
        $owner = $this->rows->orderOfNumber($number);
        if ($owner !== null) {
            return WaybillOutcome::refused('waybill_attached_elsewhere', (string)$owner);
        }
        try {
            $status = $this->oldApi->quick(self::TIMEOUT_SECONDS)->call('TrackingDocument', 'getStatusDocuments', ['Documents' => [['DocumentNumber' => $number, 'Phone' => (string)$phone]]])[0] ?? null;
        } catch (NpApiException $e) {
            return WaybillOutcome::failed(WaybillIssues::explain($e));
        }
        if (!is_array($status) || in_array((string)($status['StatusCode'] ?? ''), ['', '3'], true)) {
            return WaybillOutcome::refused('waybill_not_found');
        }
        // Code 2: deleted in the NP account; the number still answers but nothing will be sent.
        if ((string)$status['StatusCode'] === '2') {
            return WaybillOutcome::refused('waybill_deleted');
        }
        if (!$this->rows->add($orderId, $number, null, 'attached', [])) {
            // The number is unique: another request may have attached it to another order meanwhile.
            $owner = $this->rows->orderOfNumber($number);

            return $owner !== null && $owner !== $orderId
                ? WaybillOutcome::refused('waybill_attached_elsewhere', (string)$owner)
                : WaybillOutcome::refused('waybill_pending');
        }

        return new WaybillOutcome(true, $number);
    }

    public function remove(int $orderId): WaybillOutcome
    {
        $row = $this->rows->forOrder($orderId);
        if ($row === null || PendingWaybill::is($row)) {
            return WaybillOutcome::refused('waybill_none');
        }
        $ref = (string)($row->ref ?? '');
        $code = is_numeric($row->status_code ?? null) ? (int)$row->status_code : null;
        if ($row->api !== 'attached' && $code !== null && !in_array($code, WaybillStatus::DELETABLE, true)) {
            return WaybillOutcome::refused('waybill_not_deletable');
        }
        // A waybill attached by number was made elsewhere: it is only unlinked from the order; one NP
        // already deleted is only forgotten.
        if ($row->api !== 'attached' && $ref !== '' && $code !== 2) {
            try {
                if ($row->api === 'new') {
                    $this->newApi->quick(self::TIMEOUT_SECONDS)->delete('/shipments/' . rawurlencode($ref));
                } else {
                    $this->oldApi->quick(self::TIMEOUT_SECONDS)->call('InternetDocument', 'delete', ['DocumentRefs' => $ref]);
                }
            } catch (NpApiException $e) {
                return WaybillOutcome::failed(WaybillIssues::explain($e));
            }
        }
        $this->rows->remove($orderId);

        return new WaybillOutcome(true, (string)$row->number);
    }

    /**
     * Forgets the order's waybill without asking Nova Poshta: for one NP will not delete (already
     * deleted in its account, or accepted for delivery). It stays in the NP account.
     */
    public function unlink(int $orderId): WaybillOutcome
    {
        $row = $this->rows->forOrder($orderId);
        if ($row === null || PendingWaybill::is($row)) {
            return WaybillOutcome::refused('waybill_none');
        }
        $this->rows->remove($orderId);

        return new WaybillOutcome(true, (string)$row->number);
    }

    /**
     * Address by refs when every lookup succeeds; otherwise as text (NP account shows it without fields).
     *
     * @return array{0: array, 1: ?string} the save properties and the notice for a text address
     */
    private function addressRequest(WaybillDraft $draft, SenderParty $sender): array
    {
        $text = [OldDocumentRequest::forAddressText($draft, $sender), 'waybill_text_address'];
        [$lastName, $firstName] = array_pad(explode(' ', $draft->recipientName, 2), 2, '');
        $cityRef = $draft->streetRef !== null && $draft->settlementId !== null && $firstName !== '' ? $this->cities->cityRef($draft->settlementId) : null;
        if ($cityRef === null) {
            return $text;
        }
        try {
            $api = $this->oldApi->quick(self::TIMEOUT_SECONDS);
            $recipient = $api->call('Counterparty', 'save', [
                'CounterpartyProperty' => 'Recipient',
                'CounterpartyType' => 'PrivatePerson',
                'FirstName' => $firstName,
                'LastName' => $lastName,
                'Phone' => (string)$draft->recipientPhone,
            ])[0] ?? [];
            $contact = $recipient['ContactPerson']['data'][0]['Ref'] ?? null;
            $address = $this->savedAddress($api, (string)($recipient['Ref'] ?? ''), $draft);
        } catch (NpApiException) {
            return $text;
        }
        if (!is_string($contact) || !is_string($address) || empty($recipient['Ref'])) {
            return $text;
        }

        return [OldDocumentRequest::forAddressRef($draft, $sender, $cityRef, (string)$recipient['Ref'], $contact, $address), null];
    }

    /**
     * An organization's address. Its counterparty comes by EDRPOU first: a code NP does not know
     * stops here (thrown), and its ownership form is what NP needs for an address as text.
     *
     * @return array{0: array, 1: ?string} the save properties and the notice for a text address
     * @throws NpApiException when NP does not accept the organization
     */
    private function organizationAddressRequest(WaybillDraft $draft, SenderParty $sender): array
    {
        $api = $this->oldApi->quick(self::TIMEOUT_SECONDS);
        $organization = $api->call('Counterparty', 'save', [
            'CounterpartyProperty' => 'Recipient',
            'CounterpartyType' => 'Organization',
            'EDRPOU' => (string)$draft->edrpou,
        ])[0] ?? [];
        $form = is_string($organization['OwnershipForm'] ?? null) && $organization['OwnershipForm'] !== '' ? $organization['OwnershipForm'] : null;
        $text = [OldDocumentRequest::forAddressText($draft, $sender, $form), 'waybill_text_address'];
        [$lastName, $firstName] = array_pad(explode(' ', $draft->recipientName, 2), 2, '');
        $cityRef = $draft->streetRef !== null && $draft->settlementId !== null && $firstName !== '' && !empty($organization['Ref'])
            ? $this->cities->cityRef($draft->settlementId)
            : null;
        if ($cityRef === null) {
            return $text;
        }
        try {
            $contact = $api->call('ContactPerson', 'save', [
                'CounterpartyRef' => (string)$organization['Ref'],
                'FirstName' => $firstName,
                'LastName' => $lastName,
                'Phone' => (string)$draft->recipientPhone,
            ])[0]['Ref'] ?? null;
            $address = is_string($contact) ? $this->savedAddress($api, (string)$organization['Ref'], $draft) : null;
        } catch (NpApiException) {
            return $text;
        }
        if (!is_string($contact) || !is_string($address)) {
            return $text;
        }

        return [OldDocumentRequest::forAddressRef($draft, $sender, $cityRef, (string)$organization['Ref'], $contact, $address), null];
    }

    private function savedAddress(OldApiClient $api, string $counterpartyRef, WaybillDraft $draft): ?string
    {
        $ref = $api->call('AddressGeneral', 'save', array_filter([
            'CounterpartyRef' => $counterpartyRef,
            'StreetRef' => (string)$draft->streetRef,
            'BuildingNumber' => (string)$draft->house,
            'Flat' => (string)$draft->flat,
            'Note' => (string)$draft->addressNote,
        ], static fn(string $v): bool => $v !== ''))[0]['Ref'] ?? null;

        return is_string($ref) ? $ref : null;
    }

    /**
     * After an unanswered create: asks NP for the order's waybill among the account's documents of
     * the days around the attempt (both APIs file the order id as InfoRegClientBarcodes; deleted
     * ones are not listed). Found — it is the order's waybill; not found once LOOKUP_SETTLE_SECONDS have passed — the order is free again.
     */
    public function lookup(int $orderId, ?string $phone): WaybillOutcome
    {
        $row = $this->rows->forOrder($orderId);
        if (!self::needsCheck($row, ($this->now)())) {
            return WaybillOutcome::refused('waybill_none');
        }
        $at = (int)strtotime((string)($row->created_at ?? '')) ?: ($this->now)();
        try {
            $documents = $this->oldApi->quick(self::TIMEOUT_SECONDS)->call('InternetDocument', 'getDocumentList', [
                'DateTimeFrom' => date('d.m.Y', $at - 86400),
                'DateTimeTo' => date('d.m.Y', $at + 86400),
                'GetFullList' => '1',
            ]);
        } catch (NpApiException $e) {
            return WaybillOutcome::failed(WaybillIssues::explain($e));
        }
        foreach ($documents as $document) {
            $number = WaybillNumber::normalize((string)($document['IntDocNumber'] ?? ''));
            if ($number === null || (string)($document['InfoRegClientBarcodes'] ?? '') !== (string)$orderId || (int)($document['DeletionMark'] ?? 0) === 1) {
                continue;
            }
            $ref = (string)($document['Ref'] ?? '');
            if (!$this->rows->complete($orderId, $number, $ref !== '' ? $ref : null, 'old', [])) {
                return WaybillOutcome::refused('waybill_not_saved', $number);
            }
            $status = $this->status($number, $phone);
            if ($status !== null) {
                $this->rows->saveTracking($orderId, $status);
            }

            return new WaybillOutcome(true, $number, [], ['waybill_lookup_found']);
        }
        if (($this->now)() - $at < self::LOOKUP_SETTLE_SECONDS) {
            return WaybillOutcome::refused('waybill_lookup_too_soon');
        }
        $this->rows->release($orderId);

        return new WaybillOutcome(true, null, [], ['waybill_lookup_none']);
    }

    /** The waybill as NP shows it to its recipient; null when NP does not answer. */
    private function status(string $number, ?string $phone): ?array
    {
        try {
            $row = $this->oldApi->quick(self::TIMEOUT_SECONDS)->call('TrackingDocument', 'getStatusDocuments', ['Documents' => [['DocumentNumber' => $number, 'Phone' => (string)$phone]]])[0] ?? null;
        } catch (NpApiException) {
            return null;
        }

        return is_array($row) ? $row : null;
    }

    /** After these the waybill may exist at Nova Poshta: the manager checks the account before retrying. */
    private static function uncertain(NpApiException $e): bool
    {
        return $e->kind === NpErrorKind::Unavailable || $e->kind === NpErrorKind::Malformed;
    }
}
