<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Quote\AddressCityResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;

/** methodProperties of the old API's InternetDocumentGeneral.save, per kind of recipient. */
final class OldDocumentRequest
{
    private const array ADDRESS_REFS = ['CityRecipient', 'RecipientAddress', 'Recipient', 'ContactRecipient'];
    private const array ADDRESS_TEXT = ['RecipientCityName', 'RecipientAddressName', 'RecipientHouse'];
    /** The only weights NP takes for documents, ascending; it refuses any volume for them (api-docs-ua, live 09.11.2026). */
    public const array DOCUMENTS_WEIGHTS = [0.1, 0.5, 1.0];

    /** A point by its number: no recipient counterparty is created (NewAddress with names). */
    public static function forDivision(WaybillDraft $draft, SenderParty $sender): array
    {
        return self::common($draft, $sender) + [
            'NewAddress' => '1',
            'ServiceType' => 'WarehouseWarehouse',
            'RecipientWarehouseIndex' => (string)$draft->divisionNumber,
        ] + self::lockerSeat($draft) + self::recipientByName($draft) + self::money($draft);
    }

    /** An address by refs: the NP account shows every address field. */
    public static function forAddressRef(WaybillDraft $draft, SenderParty $sender, string $cityRef, string $recipientRef, string $contactRef, string $addressRef): array
    {
        return self::common($draft, $sender) + [
            'ServiceType' => 'WarehouseDoors',
            'CityRecipient' => $cityRef,
            'RecipientAddress' => $addressRef,
            'Recipient' => $recipientRef,
            'ContactRecipient' => $contactRef,
            'RecipientType' => $draft->recipientType,
        ] + self::lifting($draft) + self::money($draft);
    }

    /**
     * An address as text: NP accepts it, but its account shows the address fields empty.
     *
     * @param ?string $ownershipForm an organization's text address is refused without one; NP accepts any
     *     and keeps its registry's (live check 07.11.2026)
     */
    public static function forAddressText(WaybillDraft $draft, SenderParty $sender, ?string $ownershipForm = null): array
    {
        return self::common($draft, $sender) + array_filter([
            'NewAddress' => '1',
            'ServiceType' => 'WarehouseDoors',
            'RecipientCityName' => AddressCityResolver::plainName($draft->settlementName),
            'RecipientArea' => self::strip((string)$draft->areaName, ' область'),
            'RecipientAreaRegions' => self::strip((string)$draft->districtName, ' район'),
            'RecipientAddressName' => (string)$draft->street,
            'RecipientHouse' => (string)$draft->house,
            'RecipientFlat' => (string)$draft->flat,
            'RecipientAddressNote' => (string)$draft->addressNote,
        ], static fn(string $v): bool => $v !== '') + self::recipientByName($draft, $ownershipForm) + self::lifting($draft) + self::money($draft);
    }

    /** @return ?string language key of what is wrong with the request, checked before it is sent */
    public static function guard(array $properties): ?string
    {
        $has = static fn(string $key): bool => trim((string)($properties[$key] ?? '')) !== '';
        $service = $properties['ServiceType'] ?? null;
        if ($service === 'WarehouseWarehouse') {
            return $has('RecipientWarehouseIndex') && !$has('RecipientAddressName') && !$has('RecipientHouse') ? null : 'waybill_guard_point';
        }
        if ($service !== 'WarehouseDoors') {
            return 'waybill_guard_point';
        }
        $refs = array_filter(self::ADDRESS_REFS, $has);
        $text = array_filter(self::ADDRESS_TEXT, $has);
        $byRef = count($refs) === count(self::ADDRESS_REFS) && $text === [] && !$has('RecipientCityName');
        $byText = count($text) === count(self::ADDRESS_TEXT) && $refs === [];

        return $byRef || $byText ? null : 'waybill_guard_address';
    }

    private static function common(WaybillDraft $draft, SenderParty $sender): array
    {
        return array_filter([
            'PayerType' => $draft->payerType,
            'PaymentMethod' => $draft->paymentMethod,
            'CargoType' => $draft->cargoType,
            'SeatsAmount' => '1',
            'Description' => $draft->description,
            'Cost' => (string)(int)round($draft->declaredCost),
            'Weight' => self::number($draft->cargoType === 'Documents' ? self::documentsWeight($draft->weightKg) : $draft->weightKg),
            'VolumeGeneral' => $draft->cargoType === 'Documents' ? null : self::number($draft->volumeM3),
            'DateTime' => $draft->shipDate,
            'Sender' => $sender->counterpartyRef,
            'ContactSender' => $sender->contactRef,
            'SendersPhone' => $sender->phone,
            'SenderWarehouseIndex' => $sender->divisionNumber,
            'RecipientsPhone' => (string)$draft->recipientPhone,
            'InfoRegClientBarcodes' => (string)$draft->orderId,
        ], static fn(?string $value): bool => $value !== null) + ($draft->extraInfo !== '' ? ['AdditionalInformation' => $draft->extraInfo] : []);
    }

    private static function recipientByName(WaybillDraft $draft, ?string $ownershipForm = null): array
    {
        if ($draft->recipientType !== 'Organization') {
            return ['RecipientName' => $draft->recipientName, 'RecipientType' => 'PrivatePerson', 'RecipientContactName' => $draft->recipientName];
        }

        return [
            'RecipientName' => $draft->companyName ?? $draft->recipientName,
            'RecipientType' => 'Organization',
            'EDRPOU' => (string)$draft->edrpou,
            'RecipientContactName' => $draft->recipientName,
        ] + ($ownershipForm !== null ? ['OwnershipForm' => $ownershipForm] : []);
    }

    /**
     * A parcel locker is refused without the seat («OptionsSeat is empty»); a branch takes the general weight and volume.
     * Documents go without it: NP sizes them itself.
     */
    private static function lockerSeat(WaybillDraft $draft): array
    {
        if (!$draft->locker || $draft->cargoType === 'Documents') {
            return [];
        }
        [$length, $width, $height] = $draft->seat();

        return ['OptionsSeat' => [[
            'volumetricVolume' => self::number($draft->volumeM3),
            'volumetricLength' => (string)$length,
            'volumetricWidth' => (string)$width,
            'volumetricHeight' => (string)$height,
            'weight' => self::number($draft->weightKg),
        ]]];
    }

    private static function lifting(WaybillDraft $draft): array
    {
        return $draft->lifting && $draft->floor !== null
            ? ['NumberOfFloorsLifting' => (string)$draft->floor, 'Elevator' => $draft->elevator ? '1' : '0']
            : [];
    }

    /** Money transfer and payment control exclude each other. */
    private static function money(WaybillDraft $draft): array
    {
        if (!$draft->cod) {
            return [];
        }
        $amount = (string)(int)round($draft->codAmount);

        return $draft->codKind === CodKind::Control
            ? ['AfterpaymentOnGoodsCost' => $amount]
            : ['BackwardDeliveryData' => [['PayerType' => $draft->codPayer, 'CargoType' => 'Money', 'RedeliveryString' => $amount]]];
    }

    /** The smallest allowed weight that holds the documents; the draft keeps them within the largest. */
    private static function documentsWeight(float $kg): float
    {
        foreach (self::DOCUMENTS_WEIGHTS as $allowed) {
            if ($kg <= $allowed) {
                return $allowed;
            }
        }

        return self::DOCUMENTS_WEIGHTS[array_key_last(self::DOCUMENTS_WEIGHTS)];
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    private static function strip(string $value, string $suffix): string
    {
        $value = trim($value);

        return str_ends_with($value, $suffix) ? trim(substr($value, 0, -strlen($suffix))) : $value;
    }
}
