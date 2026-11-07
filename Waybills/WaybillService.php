<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;

/**
 * Creates, attaches and removes the waybill of an order. A create or delete is sent exactly once:
 * after a broken connection Nova Poshta may already have done it (design §4).
 */
final class WaybillService
{
    private const int TIMEOUT_SECONDS = 30;

    private readonly \Closure $today;

    /** @param ?\Closure(): string $today d.m.Y */
    public function __construct(
        private readonly OldApiClient $oldApi,
        private readonly NewApiClient $newApi,
        private readonly WaybillRows $rows,
        private readonly SenderSource $sender,
        private readonly RecipientCities $cities,
        ?\Closure $today = null,
    ) {
        $this->today = $today ?? static fn(): string => date('d.m.Y');
    }

    public function create(int $orderId, WaybillDraft $draft): WaybillOutcome
    {
        $existing = $this->rows->forOrder($orderId);
        if ($existing !== null) {
            return WaybillOutcome::refused('waybill_already', (string)$existing->number);
        }
        $sender = $this->sender->party();
        if (is_string($sender)) {
            return WaybillOutcome::refused($sender);
        }

        $notices = [];
        $route = WaybillRoute::for($draft, ($this->today)());
        if ($route === 'new') {
            $request = fn(): array => $this->newApi->quick(self::TIMEOUT_SECONDS)->post('/shipments', NewShipmentRequest::build($draft, $sender));
        } else {
            if ($draft->mode === DeliveryMode::Address) {
                [$properties, $notice] = $this->addressRequest($draft, $sender);
                $notices = array_filter([$notice]);
            } else {
                $properties = OldDocumentRequest::forDivision($draft, $sender);
            }
            $guard = OldDocumentRequest::guard($properties);
            if ($guard !== null) {
                return WaybillOutcome::refused($guard);
            }
            $request = fn(): array => $this->oldApi->quick(self::TIMEOUT_SECONDS)->callWithWarnings('InternetDocumentGeneral', 'save', $properties);
        }

        // Re-checked right before the only request that makes a waybill.
        if ($this->rows->forOrder($orderId) !== null) {
            return WaybillOutcome::refused('waybill_already', (string)$this->rows->forOrder($orderId)->number);
        }
        try {
            $answer = $request();
        } catch (NpApiException $e) {
            return WaybillOutcome::failed(self::uncertain($e) ? WaybillIssues::explain('waybill_uncertain') : WaybillIssues::explain($e));
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
            return WaybillOutcome::refused('waybill_uncertain');
        }

        $this->rows->add($orderId, $number, $ref !== '' ? $ref : null, $route, [
            'mode' => $draft->mode->value,
            'cod' => $draft->cod,
            'codAmount' => $draft->codAmount,
            'codKind' => $draft->codKind->value,
            'codPayer' => $draft->codPayer,
            'lifting' => $draft->lifting,
            'floor' => $draft->floor,
        ]);
        $audit = WaybillAudit::compare($draft, $this->status($number, $draft->recipientPhone) ?? []);
        $this->rows->saveAudit($orderId, $audit);

        return new WaybillOutcome(true, $number, [], array_values(array_unique($notices)), $audit);
    }

    public function attach(int $orderId, string $input, ?string $phone): WaybillOutcome
    {
        $number = WaybillNumber::normalize($input);
        if ($number === null) {
            return WaybillOutcome::refused('waybill_bad_number');
        }
        $existing = $this->rows->forOrder($orderId);
        if ($existing !== null) {
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
        $this->rows->add($orderId, $number, null, 'attached', []);

        return new WaybillOutcome(true, $number);
    }

    public function remove(int $orderId): WaybillOutcome
    {
        $row = $this->rows->forOrder($orderId);
        if ($row === null) {
            return WaybillOutcome::refused('waybill_none');
        }
        $ref = (string)($row->ref ?? '');
        // A waybill attached by number was made elsewhere: it is only unlinked from the order.
        if ($row->api !== 'attached' && $ref !== '') {
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
            $address = $api->call('AddressGeneral', 'save', array_filter([
                'CounterpartyRef' => (string)($recipient['Ref'] ?? ''),
                'StreetRef' => $draft->streetRef,
                'BuildingNumber' => (string)$draft->house,
                'Flat' => (string)$draft->flat,
                'Note' => (string)$draft->addressNote,
            ], static fn(string $v): bool => $v !== ''))[0]['Ref'] ?? null;
        } catch (NpApiException) {
            return $text;
        }
        if (!is_string($contact) || !is_string($address) || empty($recipient['Ref'])) {
            return $text;
        }

        return [OldDocumentRequest::forAddressRef($draft, $sender, $cityRef, (string)$recipient['Ref'], $contact, $address), null];
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
