<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;

/**
 * Turns a refusal into what the waybill form shows (design §3а, §7): the field to mark, Nova
 * Poshta's own text, our advice on what to do (a language key) and the technical details.
 * Field rules are ported from NovaPoshtaTracking DocumentIssueTranslator, with the new API's names.
 */
final class WaybillIssues
{
    /** More specific names first: RecipientAddressName before RecipientAddress. */
    private const array RULES = [
        ['~shipment_was_deleted|already deleted|was deleted~i', null, 'deleted'],
        ['~AfterpaymentOnGoodsCost is unavailable~i', 'cod_kind', 'control_unavailable'],
        // An unknown code also brings «counterparty person creation problem»: the code is the cause.
        ['~EDRPOU|OwnershipForm~i', 'edrpou', 'edrpou'],
        ['~RecipientContactName|RecipientName|recipient\.name|Empty user name|counterparty person creation|contains invalid words~i', 'recipient_name', 'recipient_name'],
        ['~RecipientsPhone|RecipientPhone|recipient\.phone|Invalid phone~i', 'phone', 'phone'],
        ['~SendersPhone|SenderPhone|sender\.phone|ContactSender|\bSender not selected|sender\.name~i', null, 'sender'],
        ['~SenderAddress|SenderWarehouse|sender\.division~i', null, 'sender_point'],
        ['~RecipientHouse|RecipientAddressName|RecipientFlat|addressParts|\bstreet\b~i', null, 'address'],
        ['~RecipientCityName|RecipientArea|CityRecipient~i', null, 'address'],
        ['~RecipientWarehouseIndex|RecipientAddress|recipient\.division|No services found|Delivery unavailable|delivery is not available~i', null, 'point'],
        ['~AfterpaymentOnGoodsCost|BackwardDelivery|Redelivery~i', 'cod_amount', 'cod'],
        ['~CargoType|cargoCategory~i', 'cargo_type', 'choice'],
        ['~PayerType~i', 'payer_type', 'choice'],
        ['~PaymentMethod~i', 'payment_method', 'choice'],
        ['~VolumeGeneral|\bvolume\b~i', 'volume', 'volume'],
        ['~VolumeWeight|actualWeight|\bWeight\b|length|width|height~i', 'weight', 'weight'],
        ['~\bCost\b|insuranceCost|declared~i', 'declared_cost', 'declared_cost'],
        ['~Description|parcelDescription~i', 'description', 'description'],
        ['~DateTime|date~i', null, 'date'],
    ];

    private const array NOTICES = [
        '~CargoType is changed to Cargo~i' => 'waybill_notice_cargo_changed',
    ];

    /**
     * @param NpApiException|string $problem Nova Poshta's refusal, or a language key of the module's own
     * @return array{field: ?string, message: ?string, advice: string, details: string}
     */
    public static function explain(NpApiException|string $problem): array
    {
        if (is_string($problem)) {
            return ['field' => null, 'message' => null, 'advice' => $problem, 'details' => ''];
        }

        $texts = array_values(array_filter(array_map(static fn($t): string => trim((string)$t), $problem->npMessages), static fn(string $t): bool => $t !== ''));
        $details = implode('; ', array_filter([
            $problem->codes === [] ? '' : 'NP ' . implode(', ', $problem->codes),
            implode(' | ', $texts),
        ]));
        $base = ['message' => $texts[0] ?? null, 'details' => $details];

        $advice = match ($problem->kind) {
            NpErrorKind::InvalidKey, NpErrorKind::Unauthorized => 'key',
            NpErrorKind::RateLimited => 'rate',
            NpErrorKind::Unavailable => 'unavailable',
            NpErrorKind::Malformed => 'generic',
            NpErrorKind::Rejected => null,
        };
        if ($advice !== null) {
            return ['field' => null, 'message' => null, 'advice' => 'waybill_advice_' . $advice, 'details' => $details];
        }

        $joined = implode(' ', $texts);
        foreach (self::RULES as [$pattern, $field, $key]) {
            if (preg_match($pattern, $joined)) {
                return ['field' => $field, 'advice' => 'waybill_advice_' . $key] + $base;
            }
        }

        return ['field' => null, 'advice' => 'waybill_advice_generic'] + $base;
    }

    /**
     * Warnings of a waybill Nova Poshta accepted, as language keys; ones we cannot explain are left out.
     *
     * @param list<string> $warnings
     * @return list<string>
     */
    public static function notices(array $warnings): array
    {
        $keys = [];
        foreach ($warnings as $warning) {
            foreach (self::NOTICES as $pattern => $key) {
                if (preg_match($pattern, $warning)) {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }
}
