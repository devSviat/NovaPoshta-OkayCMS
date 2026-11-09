<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Quote\AddressCityResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;

/**
 * Compares a created waybill, as getStatusDocuments shows it, with what was ordered: Nova Poshta
 * accepts a waybill and silently drops or changes a service.
 */
final class WaybillAudit
{
    /** @param array<string, mixed> $status one item of TrackingDocument.getStatusDocuments, asked with the recipient's phone */
    public static function compare(WaybillDraft $draft, array $status): AuditResult
    {
        $unchecked = $draft->lifting ? ['lifting'] : [];
        // Without the recipient's phone NP hides the recipient and the money fields: nothing to compare.
        $known = in_array((string)($status['StatusCode'] ?? ''), ['', '3'], true) === false
            && trim((string)($status['RecipientFullName'] ?? '')) !== '';
        if (!$known) {
            return new AuditResult('unverified', [], $unchecked);
        }

        $findings = [];
        $add = static function (string $kind, string $expected, string $actual) use (&$findings): void {
            $findings[] = ['kind' => $kind, 'expected' => $expected, 'actual' => $actual];
        };
        $service = (string)($status['ServiceType'] ?? '');
        $toDoor = $draft->mode === DeliveryMode::Address;
        if ($toDoor !== str_ends_with($service, 'Doors')) {
            $add('service_type', $toDoor ? 'WarehouseDoors' : 'WarehouseWarehouse', $service);
        }

        $city = AddressCityResolver::plainName($draft->settlementName);
        if (!self::same($city, (string)($status['CityRecipient'] ?? ''))) {
            $add('city', $city, (string)($status['CityRecipient'] ?? ''));
        }

        if ($toDoor) {
            $address = AddressNames::normalize((string)($status['RecipientAddress'] ?? ''));
            $street = AddressNames::streetCore((string)$draft->street);
            $house = preg_quote(AddressNames::normalize((string)$draft->house), '/');
            if ($street === '' || !str_contains($address, $street) || !preg_match('/(?<![\p{L}\d])' . $house . '(?![\p{L}\d])/u', $address)) {
                $add('address', trim($draft->street . ', ' . $draft->house), (string)($status['RecipientAddress'] ?? ''));
            }
        } elseif (trim((string)($status['WarehouseRecipient'] ?? '')) === '') {
            // A new-API waybill gets its point in this answer only a while after it is made (live 07.11.2026).
            $unchecked[] = 'point';
        } elseif (!self::same((string)$draft->divisionName, (string)$status['WarehouseRecipient'])) {
            $add('point', (string)$draft->divisionName, (string)$status['WarehouseRecipient']);
        }

        $amount = (string)(int)round($draft->codAmount);
        $wantsMoney = $draft->cod && $draft->codKind === CodKind::Money;
        $hasMoney = (string)($status['Redelivery'] ?? '0') === '1';
        $moneySum = (string)(int)round((float)($status['RedeliverySum'] ?? 0));
        if ($wantsMoney !== $hasMoney || ($wantsMoney && $moneySum !== $amount)) {
            $add('cod', $wantsMoney ? $amount : '0', $hasMoney ? $moneySum : '0');
        }
        $payer = (string)($status['RedeliveryPayer'] ?? '');
        if ($wantsMoney && $hasMoney && $payer !== $draft->codPayer) {
            // For 10–20 s after creation Nova Poshta reports its default, Sender, whatever was sent (live 09.11.2026).
            if ($payer === 'Sender') {
                $unchecked[] = 'cod_payer';
            } else {
                $add('cod_payer', $draft->codPayer, $payer);
            }
        }

        $wantsControl = $draft->cod && $draft->codKind === CodKind::Control;
        $control = (string)(int)round((float)($status['AfterpaymentOnGoodsCost'] ?? 0));
        if ($wantsControl ? $control !== $amount : $control !== '0') {
            $add('control', $wantsControl ? $amount : '0', $control);
        }

        return new AuditResult($findings === [] ? 'verified' : 'mismatch', $findings, $unchecked);
    }

    private static function same(string $a, string $b): bool
    {
        return AddressNames::normalize($a) === AddressNames::normalize($b);
    }
}
