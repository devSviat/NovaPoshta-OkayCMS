<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Quote\AddressCityResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;

/**
 * Порівнює створену накладну, як її показує getStatusDocuments, із замовленим: Нова Пошта
 * приймає накладну й мовчки відкидає або змінює послугу.
 */
final class WaybillAudit
{
    /** @param array<string, mixed> $status один елемент TrackingDocument.getStatusDocuments, запитаний з телефоном отримувача */
    public static function compare(WaybillDraft $draft, array $status): AuditResult
    {
        $unchecked = $draft->lifting ? ['lifting'] : [];
        // Без телефону отримувача НП приховує отримувача й грошові поля: порівнювати нічого.
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
            // Накладна нового API отримує свій пункт видачі в цій відповіді лише через деякий час після створення (live 07.11.2026).
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
            // Перші 10–20 с після створення Нова Пошта повідомляє своє значення за замовчуванням, Sender, хоч би що надіслали (live 09.11.2026).
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
