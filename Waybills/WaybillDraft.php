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

    /**
     * @param 'Recipient'|'Sender' $payerType
     * @param 'Cash'|'NonCash' $paymentMethod
     * @param 'Parcel'|'Cargo'|'Documents' $cargoType
     * @param 'Recipient'|'Sender' $codPayer
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
        /** In hryvnias. */
        public float $codAmount,
        public CodKind $codKind,
        public string $codPayer,
        /** d.m.Y */
        public string $shipDate,
    ) {
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
        if ($cod && ($codAmount === null || $codAmount <= 0)) {
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
        $note = $this->mode === DeliveryMode::Address ? mb_substr($text('address_note', (string)$this->addressNote), 0, self::NOTE_MAX) : null;

        $values = [
            'recipientName' => $name,
            'recipientPhone' => $phone,
            'weightKg' => (float)$weight,
            'volumeM3' => (float)$volume,
            'declaredCost' => (float)$declared,
            'description' => $description,
            'cargoType' => $choice('cargo_type', $this->cargoType, ['Parcel', 'Cargo', 'Documents']),
            'payerType' => $choice('payer_type', $this->payerType, ['Recipient', 'Sender']),
            'paymentMethod' => $choice('payment_method', $this->paymentMethod, ['Cash', 'NonCash']),
            'codPayer' => $choice('cod_payer', $this->codPayer, ['Recipient', 'Sender']),
            'cod' => $cod,
            'codAmount' => (float)$codAmount,
            'codKind' => $codKind ?? $this->codKind,
            'lifting' => $lifting,
            'floor' => $lifting ? (int)$floor : $this->floor,
            'elevator' => $this->mode === DeliveryMode::Address && (array_key_exists('elevator', $form) ? !empty($form['elevator']) : $this->elevator),
            'addressNote' => $note === '' ? null : $note,
        ];

        return $errors !== [] ? $errors : clone($this, $values);
    }
}
