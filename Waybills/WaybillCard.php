<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/**
 * The waybill card as NovaPoshtaTracking drew it (author's choice): values formatted from the
 * last getStatusDocuments answer. Words NP sends as codes (payer, cargo type) become language keys.
 */
final class WaybillCard
{
    private const string KEY = 'sviat__novaposhta__';
    private const int ADDRESS_MAX = 70;
    /** NP's volumetric weight: kg per m³. */
    private const int VOLUME_TO_KG = 250;

    /** @param ?array $document an item of TrackingDocument.getStatusDocuments, null before the first refresh */
    public static function fromDocument(?array $document, object $row): array
    {
        $number = (string)$row->number;
        $code = isset($row->status_code) ? (int)$row->status_code : null;
        $ref = (string)($row->ref ?? '');
        $card = [
            'number' => $number,
            'numberSpaced' => self::spaced($number),
            // A just-created waybill can still be edited in the NP account.
            'link' => $code === 1 && $ref !== '' ? 'https://new.novaposhta.ua/edit/' . rawurlencode($ref) : 'https://novaposhta.ua/tracking/?cargo_number=' . rawurlencode($number),
            'statusGroup' => WaybillStatus::group($code),
            'statusKey' => $code ? self::KEY . WaybillStatus::textKey($code) : null,
            // When the details were fetched, and when the status was last checked: two different times.
            'updated' => self::dateTime($document['_fetched_at'] ?? null, 'Y-m-d H:i:s'),
            'checked' => self::dateTime($row->tracked_at ?? null, 'Y-m-d H:i:s'),
            'created' => null, 'description' => null, 'announced' => null,
            'route' => null, 'recipient' => null, 'services' => null, 'payment' => null, 'more' => null,
        ];
        if ($document === null) {
            return $card;
        }
        $text = static fn(string $key): ?string => is_scalar($document[$key] ?? null) && trim((string)$document[$key]) !== '' ? trim((string)$document[$key]) : null;
        $amount = static fn(string $key): ?float => is_numeric($document[$key] ?? null) && (float)$document[$key] > 0 ? (float)$document[$key] : null;

        $card['created'] = self::dateTime($text('DateCreated'), 'd-m-Y H:i:s');
        $card['description'] = $text('CargoDescriptionString');
        $card['announced'] = self::money($amount('AnnouncedPrice'));
        $card['route'] = [
            'from' => self::cut($text('WarehouseSenderAddress') ?? $text('SenderAddress')),
            'status' => trim((string)($row->status_text ?? '')) !== '' ? trim((string)$row->status_text) : $text('Status'),
            'statusGroup' => $card['statusGroup'],
            'plan' => self::date($text('ScheduledDeliveryDate')),
            'to' => self::cut($text('WarehouseRecipientAddress') ?? $text('RecipientAddress')),
        ];
        $name = $text('RecipientFullName') ?? $text('RecipientFullNameEW');
        if (($document['CounterpartyType'] ?? null) === 'Organization' && $text('CounterpartyRecipientDescription') !== null) {
            $name = trim($text('CounterpartyRecipientDescription') . ', ' . (string)$name, ', ');
        }
        $card['recipient'] = [
            'name' => $name,
            'phone' => self::phone($text('PhoneRecipient')),
            'received' => self::dateTime($text('RecipientDateTime'), 'd.m.Y H:i:s'),
            'address' => $text('RecipientAddress'),
        ];
        $basis = $text('LastCreatedOnTheBasisDocumentType');
        $card['services'] = [
            'redelivery' => (string)($document['Redelivery'] ?? '') === '1' ? self::money($amount('RedeliverySum')) : null,
            'redeliveryPayer' => self::word('waybill_party_', $text('RedeliveryPayer')),
            'redeliveryNumber' => $text('RedeliveryNum'),
            'control' => self::money($amount('AfterpaymentOnGoodsCost')),
            'returnOrRedirect' => in_array($basis, ['CargoReturn', 'Redirecting'], true)
                ? ['kind' => self::KEY . ($basis === 'CargoReturn' ? 'waybill_return' : 'waybill_redirect'), 'number' => $text('LastCreatedOnTheBasisNumber')]
                : null,
        ];
        $card['payment'] = [
            'cost' => self::money($amount('DocumentCost')),
            'payer' => self::word('waybill_party_', $text('PayerType')),
            'method' => match ($text('PaymentMethod')) {
                'Cash' => self::KEY . 'waybill_cash',
                'NonCash' => self::KEY . 'waybill_noncash',
                default => null,
            },
        ];
        $volume = $amount('VolumeGeneral');
        $card['more'] = [
            'type' => self::word('waybill_cargo_', $text('CargoType') === null ? null : strtolower((string)$text('CargoType'))),
            'weight' => self::kg($amount('DocumentWeight') ?? $amount('Weight')),
            'volumeWeight' => self::kg($volume !== null ? $volume * self::VOLUME_TO_KG : $amount('VolumeWeight')),
            'seats' => $text('SeatsAmount') ?? '1',
            'barcode' => $text('ClientBarcode'),
            'factualWeight' => self::kg($amount('FactualWeight')),
            'serviceType' => self::word('waybill_service_', $text('ServiceType')),
        ];

        return $card;
    }

    /** "20451581331601" → "20 4515 8133 1601", as NP prints it. */
    private static function spaced(string $number): string
    {
        return strlen($number) === 14 ? substr($number, 0, 2) . ' ' . implode(' ', str_split(substr($number, 2), 4)) : $number;
    }

    private static function money(?float $value): ?string
    {
        return $value === null ? null : self::number($value) . ' ₴';
    }

    private static function kg(?float $value): ?string
    {
        return $value === null ? null : self::number($value) . ' кг';
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private static function word(string $prefix, ?string $value): ?string
    {
        return $value !== null && preg_match('/^[A-Za-z]+$/', $value) ? self::KEY . $prefix . $value : null;
    }

    private static function cut(?string $address): ?string
    {
        return $address === null || mb_strlen($address) <= self::ADDRESS_MAX ? $address : rtrim(mb_substr($address, 0, self::ADDRESS_MAX - 1)) . '…';
    }

    private static function phone(?string $phone): ?string
    {
        return $phone !== null && preg_match('/^380(\d{2})(\d{3})(\d{2})(\d{2})$/', $phone, $m) ? "+380 {$m[1]} {$m[2]} {$m[3]} {$m[4]}" : $phone;
    }

    private static function date(?string $value): ?string
    {
        $date = $value === null ? false : \DateTimeImmutable::createFromFormat('d-m-Y H:i:s', $value);

        return $date ? $date->format('d.m.Y') : null;
    }

    private static function dateTime(mixed $value, string $format): ?string
    {
        $date = is_string($value) && $value !== '' ? \DateTimeImmutable::createFromFormat($format, $value) : false;

        return $date ? $date->format('d.m.Y H:i') : null;
    }
}
