<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;

/**
 * Створює, прив'язує й видаляє накладну замовлення. Створення чи видалення надсилається рівно один раз:
 * після обриву з'єднання Нова Пошта могла вже це зробити.
 */
final class WaybillService
{
    private const int TIMEOUT_SECONDS = 30;
    /** Резервація, старша за це, належить запиту, що помер (виклики НП обриваються по таймауту за 30 с). */
    private const int PENDING_STALE_SECONDS = 120;
    /** Відправлення нового API з'являється в списку документів старого API лише згодом; доти «немає» нічого не доводить. */
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

    /** Резервація запиту, що ще виконується; застаріла — ні. */
    public static function isPending(?object $row, int $now): bool
    {
        return PendingWaybill::is($row) && !self::needsCheck($row, $now);
    }

    /**
     * Резервація, запит якої міг створити накладну в НП: НП на нього не відповіла, або запит
     * помер посередині. Для замовлення нічого нового не створюється, доки lookup() не запитав НП.
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
            // НП відповіла відмовою: нічого не створено.
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
            // Та сама відповідь заповнює картку: другого запиту немає.
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
        // Код 2: видалено в кабінеті НП; номер ще відповідає, але нічого не буде відправлено.
        if ((string)$status['StatusCode'] === '2') {
            return WaybillOutcome::refused('waybill_deleted');
        }
        if (!$this->rows->add($orderId, $number, null, 'attached', [])) {
            // Номер унікальний: тим часом інший запит міг прив'язати його до іншого замовлення.
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
        // Накладну, прив'язану за номером, створено деінде: її лише відв'язують від замовлення; ту, яку НП
        // уже видалила, лише забувають.
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
     * Забуває накладну замовлення, не питаючи Нову Пошту: для тієї, яку НП не видалить (уже
     * видалена в кабінеті або прийнята до доставки). У кабінеті НП вона лишається.
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
     * Адреса за refs, коли кожен пошук вдався; інакше текстом (кабінет НП показує її без полів).
     *
     * @return array{0: array, 1: ?string} властивості для save і повідомлення для текстової адреси
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
     * Адреса організації. Її контрагента спершу шукають за ЄДРПОУ: на коді, якого НП не знає, усе зупиняється
     * (виняток), а його форма власності — те, що потрібно НП для адреси текстом.
     *
     * @return array{0: array, 1: ?string} властивості для save і повідомлення для текстової адреси
     * @throws NpApiException коли НП не приймає організацію
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
     * Після створення без відповіді: шукає в НП накладну замовлення серед документів кабінету за
     * дні навколо спроби (обидва API записують id замовлення як InfoRegClientBarcodes; видалені
     * не показуються). Знайдено — це накладна замовлення; не знайдено після LOOKUP_SETTLE_SECONDS — замовлення знову вільне.
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

    /** Накладна, як НП показує її отримувачу; null, коли НП не відповідає. */
    private function status(string $number, ?string $phone): ?array
    {
        try {
            $row = $this->oldApi->quick(self::TIMEOUT_SECONDS)->call('TrackingDocument', 'getStatusDocuments', ['Documents' => [['DocumentNumber' => $number, 'Phone' => (string)$phone]]])[0] ?? null;
        } catch (NpApiException) {
            return null;
        }

        return is_array($row) ? $row : null;
    }

    /** Після цих накладна може існувати в Новій Пошті: менеджер перевіряє кабінет перед повтором. */
    private static function uncertain(NpApiException $e): bool
    {
        return $e->kind === NpErrorKind::Unavailable || $e->kind === NpErrorKind::Malformed;
    }
}
