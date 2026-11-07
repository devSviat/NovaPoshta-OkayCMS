<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Core\Settings;

/** The "Waybills" block of the settings page. */
final class WaybillSettings
{
    private const string PREFIX = 'sviat__novaposhta__waybill_';
    private const array FIELDS = ['contact_ref', 'sender_phone', 'cutoff', 'payer_type', 'payment_method', 'cargo_type', 'cod_payer', 'description'];
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

        // Stored values were valid when saved; a broken one falls back to the defaults.
        return $config instanceof WaybillConfig ? $config : self::parse([]);
    }

    /** @return list<string> language keys of the errors; nothing is saved when there are any */
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
        ] as $field => $value) {
            $this->settings->set(self::PREFIX . $field, $value ?? '');
        }

        return [];
    }

    /** @return WaybillConfig|list<string> the values, or the language keys of what is wrong */
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
        $payer = $choice($text('payer_type'), ['Recipient', 'Sender'], 'waybill_error_payer');
        $payment = $choice($text('payment_method'), ['Cash', 'NonCash'], 'waybill_error_payment');
        $cargo = $choice($text('cargo_type'), ['Parcel', 'Cargo', 'Documents'], 'waybill_error_cargo');
        $codPayer = $choice($text('cod_payer'), ['Recipient', 'Sender'], 'waybill_error_cod_payer');
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
        );
    }
}
