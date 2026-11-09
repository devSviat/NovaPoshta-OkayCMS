<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;

/** Everything one waybill is made of: first from the order and the settings, then as the manager edits it. */
final readonly class WaybillDraft
{
    private const float MAX_WEIGHT = 1000.0;
    private const float MAX_VOLUME = 10.0;
    private const int NAME_MAX = 60;
    private const int DESCRIPTION_MAX = 100;
    private const int NOTE_MAX = 50;
    private const int FLOOR_MAX = 200;
    private const int COMPANY_MAX = 100;
    /** AdditionalInformation as the old module limited it. */
    public const int EXTRA_INFO_MAX = 100;
    /** Parcel-locker cell limits, as the old NovaPoshtaTracking checked them: length, width, height in cm; weight in kg. */
    private const array LOCKER_MAX_CM = ['seat_length' => 60, 'seat_width' => 40, 'seat_height' => 30];
    private const float LOCKER_MAX_KG = 20.0;

    /**
     * @param 'Recipient'|'Sender' $payerType
     * @param 'Cash'|'NonCash' $paymentMethod
     * @param 'Parcel'|'Cargo'|'Documents' $cargoType
     * @param 'Recipient'|'Sender' $codPayer
     * @param 'PrivatePerson'|'Organization' $recipientType
     */
    public function __construct(
        public int $orderId,
        public DeliveryMode $mode,
        public ?int $divisionId,
        public ?string $divisionNumber,
        public ?string $divisionName,
        public ?int $settlementId,
        public string $settlementName,
        public ?string $areaName,
        public ?string $districtName,
        public ?string $street,
        public ?string $streetRef,
        public ?string $house,
        public ?string $flat,
        public ?string $addressNote,
        public bool $lifting,
        public ?int $floor,
        public bool $elevator,
        public string $recipientName,
        public ?string $recipientPhone,
        public float $weightKg,
        public float $volumeM3,
        /** In hryvnias, goods without delivery. */
        public float $declaredCost,
        public string $description,
        public string $cargoType,
        public string $payerType,
        public string $paymentMethod,
        public bool $cod,
        /** Whole hryvnias: the old API takes RedeliveryString as an integer, the card shows what is sent. */
        public float $codAmount,
        public CodKind $codKind,
        public string $codPayer,
        /** d.m.Y */
        public string $shipDate,
        public string $recipientType = 'PrivatePerson',
        /** Digits only. */
        public ?string $edrpou = null,
        /** For the manager only: Nova Poshta names the organization from its registry by the code. */
        public ?string $companyName = null,
        /** The organization came from the order's invoice (Sviat/InvoicePayment). */
        public bool $fromInvoice = false,
        /** Hryvnias of delivery the order total (and so the COD amount) already holds; 0 when paid apart. */
        public float $deliveryInTotal = 0.0,
        /** AdditionalInformation: what is inside, for the sender's eyes (SKUs by default). */
        public string $extraInfo = '',
        /** Why the draft made the sender pay: 'in_total', 'free' or null — for the form's note. */
        public ?string $senderPaysBecause = null,
        /** The point is a parcel locker: the old API needs the seat's size for it. */
        public bool $locker = false,
        /** @var ?array{int, int, int} length, width, height in cm the manager set */
        public ?array $seatCm = null,
    ) {
    }

    /**
     * The seat for a parcel locker in cm: what the manager set, else a cube of the volume
     * (0.001 m³ gives 10 × 10 × 10, what the old module started with).
     *
     * @return array{int, int, int}
     */
    public function seat(): array
    {
        $side = max(1, (int)round(($this->volumeM3 ** (1 / 3)) * 100));

        return $this->seatCm ?? [$side, $side, $side];
    }

    /** @param array<string, mixed> $values property name => new value */
    public function with(array $values): self
    {
        return clone($this, $values);
    }

    /** The recipient pays Nova Poshta for delivery the order total already includes. */
    public function chargesDeliveryTwice(): bool
    {
        return $this->payerType === 'Recipient' && $this->deliveryInTotal > 0;
    }

    /** @return ?string language key of what is wrong with the organization; NP checks the code against its registry itself */
    public function organizationIssue(): ?string
    {
        if ($this->recipientType !== 'Organization') {
            return null;
        }

        return preg_match('/^(\d{8}|\d{10})$/', (string)$this->edrpou) ? null : 'waybill_error_edrpou';
    }

    /**
     * The manager's form on top of this draft. A missing `cod` means its checkbox is off; other
     * missing fields keep the draft's value.
     *
     * @return self|list<array{field: string, key: string}>
     */
    public function withInput(array $form): self|array
    {
        $errors = [];
        $text = static fn(string $name, string $current): string => is_string($form[$name] ?? null) ? trim($form[$name]) : $current;
        $number = static function (string $name, ?float $current) use ($form): ?float {
            if (!array_key_exists($name, $form)) {
                return $current;
            }
            $raw = is_string($form[$name]) || is_int($form[$name]) || is_float($form[$name]) ? str_replace(',', '.', trim((string)$form[$name])) : '';

            return is_numeric($raw) ? (float)$raw : null;
        };
        $choice = static function (string $name, string $current, array $allowed) use ($form, &$errors): string {
            $value = is_string($form[$name] ?? null) ? $form[$name] : $current;
            if (!in_array($value, $allowed, true)) {
                $errors[] = ['field' => $name, 'key' => 'waybill_error_choice'];

                return $current;
            }

            return $value;
        };

        $name = preg_replace('/\s+/u', ' ', $text('recipient_name', $this->recipientName));
        if ($name === '' || mb_strlen($name) > self::NAME_MAX) {
            $errors[] = ['field' => 'recipient_name', 'key' => 'waybill_error_recipient_name'];
        }
        $phone = array_key_exists('phone', $form) ? NpPhone::normalize(is_string($form['phone']) ? $form['phone'] : '') : $this->recipientPhone;
        if ($phone === null) {
            $errors[] = ['field' => 'phone', 'key' => 'waybill_error_recipient_phone'];
        }
        $weight = $number('weight', $this->weightKg);
        if ($weight === null || $weight <= 0 || $weight > self::MAX_WEIGHT) {
            $errors[] = ['field' => 'weight', 'key' => 'waybill_error_weight'];
        }
        $volume = $number('volume', $this->volumeM3);
        if ($volume === null || $volume <= 0 || $volume > self::MAX_VOLUME) {
            $errors[] = ['field' => 'volume', 'key' => 'waybill_error_volume'];
        }
        $declared = $number('declared_cost', $this->declaredCost);
        if ($declared === null || $declared < 0) {
            $errors[] = ['field' => 'declared_cost', 'key' => 'waybill_error_declared_cost'];
        }
        $description = $text('description', $this->description);
        if ($description === '' || mb_strlen($description) > self::DESCRIPTION_MAX) {
            $errors[] = ['field' => 'description', 'key' => 'waybill_error_description'];
        }
        $cod = !empty($form['cod']);
        $codAmount = $number('cod_amount', $this->codAmount);
        // NP keeps the money transfer in whole hryvnias, rounding to the nearest: what rounds to 0 is no amount.
        if ($cod && ($codAmount === null || round($codAmount) <= 0)) {
            $errors[] = ['field' => 'cod_amount', 'key' => 'waybill_error_cod_amount'];
        }
        $codKind = is_string($form['cod_kind'] ?? null) ? CodKind::tryFrom($form['cod_kind']) : $this->codKind;
        if ($codKind === null) {
            $errors[] = ['field' => 'cod_kind', 'key' => 'waybill_error_choice'];
        }
        $lifting = $this->mode === DeliveryMode::Address && (array_key_exists('lifting', $form) ? !empty($form['lifting']) : $this->lifting);
        $floor = $number('floor', $this->floor === null ? null : (float)$this->floor);
        if ($lifting && ($floor === null || $floor < 1 || $floor > self::FLOOR_MAX || $floor != (int)$floor)) {
            $errors[] = ['field' => 'floor', 'key' => 'waybill_error_floor'];
        }
        $seat = null;
        if ($this->locker) {
            if ($weight !== null && $weight > self::LOCKER_MAX_KG) {
                $errors[] = ['field' => 'weight', 'key' => 'waybill_error_locker_weight'];
            }
            $seat = [];
            foreach (array_combine(array_keys(self::LOCKER_MAX_CM), $this->seat()) as $field => $current) {
                $side = $number($field, (float)$current);
                if ($side === null || $side < 1 || $side > self::LOCKER_MAX_CM[$field]) {
                    $errors[] = ['field' => $field, 'key' => 'waybill_error_locker_seat'];
                }
                $seat[] = (int)round((float)$side);
            }
        }
        $extraInfo = $text('extra_info', $this->extraInfo);
        if (mb_strlen($extraInfo) > self::EXTRA_INFO_MAX) {
            $errors[] = ['field' => 'extra_info', 'key' => 'waybill_error_extra_info'];
        }
        $cargoType = $choice('cargo_type', $this->cargoType, ['Parcel', 'Cargo', 'Documents']);
        if ($this->locker && $cargoType === 'Cargo') {
            $errors[] = ['field' => 'cargo_type', 'key' => 'waybill_error_locker_cargo'];
        }
        $note = $this->mode === DeliveryMode::Address ? mb_substr($text('address_note', (string)$this->addressNote), 0, self::NOTE_MAX) : null;

        $recipientType = $choice('recipient_type', $this->recipientType, ['PrivatePerson', 'Organization']);
        $organization = $recipientType === 'Organization';
        $edrpou = $organization ? preg_replace('/\D+/', '', $text('edrpou', (string)$this->edrpou)) : null;
        $company = $organization ? mb_substr($text('company_name', (string)$this->companyName), 0, self::COMPANY_MAX) : null;

        $values = [
            'recipientType' => $recipientType,
            'edrpou' => $edrpou === '' ? null : $edrpou,
            'companyName' => $company === '' ? null : $company,
            'recipientName' => $name,
            'recipientPhone' => $phone,
            'weightKg' => (float)$weight,
            'volumeM3' => (float)$volume,
            'declaredCost' => (float)$declared,
            'description' => $description,
            'cargoType' => $cargoType,
            'extraInfo' => $extraInfo,
            'payerType' => $choice('payer_type', $this->payerType, ['Recipient', 'Sender']),
            'paymentMethod' => $choice('payment_method', $this->paymentMethod, ['Cash', 'NonCash']),
            'codPayer' => $choice('cod_payer', $this->codPayer, ['Recipient', 'Sender']),
            'cod' => $cod,
            'codAmount' => round((float)$codAmount),
            'codKind' => $codKind ?? $this->codKind,
            'lifting' => $lifting,
            'floor' => $lifting ? (int)$floor : $this->floor,
            'elevator' => $this->mode === DeliveryMode::Address && (array_key_exists('elevator', $form) ? !empty($form['elevator']) : $this->elevator),
            'addressNote' => $note === '' ? null : $note,
            'seatCm' => $seat ?? $this->seatCm,
        ];

        if ($errors === []) {
            $draft = clone($this, $values);
            $issue = $draft->organizationIssue();

            return $issue === null ? $draft : [['field' => 'edrpou', 'key' => $issue]];
        }

        return $errors;
    }
}
