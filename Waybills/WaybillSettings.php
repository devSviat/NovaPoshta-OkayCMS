<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Core\Settings;

/** Блок «Накладні» сторінки налаштувань. */
final class WaybillSettings
{
    private const string PREFIX = 'sviat__novaposhta__waybill_';
    private const array FIELDS = ['contact_ref', 'sender_phone', 'cutoff', 'payer_type', 'payment_method', 'cargo_type', 'cod_payer', 'description', 'description_mode', 'extra_info', 'cargo_types'];
    private const int DESCRIPTION_MAX = 100;

    public function __construct(private readonly Settings $settings)
    {
    }

    public function read(): WaybillConfig
    {
        $stored = [];
        foreach (self::FIELDS as $field) {
            $value = $this->settings->get(self::PREFIX . $field);
            if (is_string($value) && $value !== '') {
                $stored[$field] = $value;
            }
        }
        $config = self::parse($stored);

        // Збережені значення були коректними на момент збереження; зламане повертається до значень за замовчуванням.
        return $config instanceof WaybillConfig ? $config : self::parse([]);
    }

    /** @return list<string> мовні ключі помилок; коли вони є, нічого не зберігається */
    public function save(array $post): array
    {
        $config = self::parse($post);
        if (!$config instanceof WaybillConfig) {
            return $config;
        }
        foreach ([
            'contact_ref' => $config->contactRef,
            'sender_phone' => $config->senderPhone,
            'cutoff' => $config->cutoff,
            'payer_type' => $config->payerType,
            'payment_method' => $config->paymentMethod,
            'cargo_type' => $config->cargoType,
            'cod_payer' => $config->codPayer,
            'description' => $config->description,
            'description_mode' => $config->descriptionMode,
            'extra_info' => $config->extraInfo,
            'cargo_types' => implode(',', $config->cargoTypes),
        ] as $field => $value) {
            $this->settings->set(self::PREFIX . $field, $value ?? '');
        }

        return [];
    }

    /** @return WaybillConfig|list<string> значення або мовні ключі того, що не так */
    public static function parse(array $post): WaybillConfig|array
    {
        $errors = [];
        $text = static fn(string $name): string => is_string($post[$name] ?? null) ? trim($post[$name]) : '';

        $contact = $text('contact_ref');
        if ($contact !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $contact)) {
            $errors[] = 'waybill_error_contact';
        }
        $phone = null;
        if ($text('sender_phone') !== '') {
            $phone = NpPhone::normalize($text('sender_phone'));
            if ($phone === null) {
                $errors[] = 'waybill_error_phone';
            }
        }
        $cutoff = $text('cutoff') === '' ? WaybillConfig::DEFAULT_CUTOFF : $text('cutoff');
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $cutoff, $m)) {
            $cutoff = sprintf('%02d:%s', (int)$m[1], $m[2]);
        } else {
            $errors[] = 'waybill_error_cutoff';
        }
        $choice = static function (string $value, array $allowed, string $error) use (&$errors): string {
            if ($value === '') {
                return $allowed[0];
            }
            if (!in_array($value, $allowed, true)) {
                $errors[] = $error;
            }

            return $value;
        };
        // 'auto' було значенням за замовчуванням до 1.0.0; чернетки однаково роблять платником відправника, де треба.
        $payer = $choice($text('payer_type') === 'auto' ? '' : $text('payer_type'), ['Recipient', 'Sender'], 'waybill_error_payer');
        $payment = $choice($text('payment_method'), ['Cash', 'NonCash'], 'waybill_error_payment');
        $cargoTypes = self::cargoTypes($post['cargo_types'] ?? null, $errors);
        $cargo = $choice($text('cargo_type'), $cargoTypes === [] ? WaybillConfig::CARGO_TYPES : $cargoTypes, 'waybill_error_cargo');
        if ($cargoTypes !== [] && !in_array($cargo, $cargoTypes, true) && in_array($cargo, WaybillConfig::CARGO_TYPES, true)) {
            array_pop($errors);
            $errors[] = 'waybill_error_cargo_default';
        }
        $codPayer = $choice($text('cod_payer'), ['Recipient', 'Sender'], 'waybill_error_cod_payer');
        $descriptionMode = $choice($text('description_mode'), ['order', 'text'], 'waybill_error_choice');
        $extraInfo = $choice($text('extra_info'), ['skus', 'names', 'none'], 'waybill_error_choice');
        $description = $text('description') === '' ? WaybillConfig::DEFAULT_DESCRIPTION : $text('description');
        if (mb_strlen($description) > self::DESCRIPTION_MAX) {
            $errors[] = 'waybill_error_description';
        }

        return $errors !== [] ? $errors : new WaybillConfig(
            $contact === '' ? null : $contact,
            $phone,
            $cutoff,
            $payer,
            $payment,
            $cargo,
            $codPayer,
            $description,
            $descriptionMode,
            $extraInfo,
            $cargoTypes,
        );
    }

    /**
     * Форма надсилає порожній маркер плюс позначені прапорці, тож «нічого не позначено» відрізняється від «не надіслано»;
     * у сховищі — список через кому. Зовсім нічого означає значення за замовчуванням.
     *
     * @param list<string> $errors
     * @return list<string> у порядку WaybillConfig::CARGO_TYPES; порожній лише разом із помилкою
     */
    private static function cargoTypes(mixed $raw, array &$errors): array
    {
        if ($raw === null || $raw === '') {
            return WaybillConfig::CARGO_TYPES;
        }
        $given = array_values(array_filter(
            is_array($raw) ? $raw : explode(',', is_string($raw) ? $raw : ''),
            static fn(mixed $type): bool => is_string($type) && $type !== '',
        ));
        if (array_diff($given, WaybillConfig::CARGO_TYPES) !== []) {
            $errors[] = 'waybill_error_cargo';

            return [];
        }
        if ($given === []) {
            $errors[] = 'waybill_error_cargo_types';
        }

        return array_values(array_intersect(WaybillConfig::CARGO_TYPES, $given));
    }
}
