<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** Форма накладної, як її надіслано: лише відомі поля, текст обрізано до ліміту; решту перевіряє WaybillDraft::withInput. */
final class WaybillInput
{
    private const array TEXT = [
        'recipient_name' => 60,
        'phone' => 20,
        'description' => 100,
        'address_note' => 50,
        'cargo_type' => 16,
        'payer_type' => 16,
        'payment_method' => 16,
        'cod_kind' => 16,
        'cod_payer' => 16,
        'recipient_type' => 16,
        'edrpou' => 20,
        'company_name' => 100,
        'extra_info' => WaybillDraft::EXTRA_INFO_MAX,
    ];
    private const array NUMBERS = ['weight', 'volume', 'declared_cost', 'cod_amount', 'floor', 'seat_length', 'seat_width', 'seat_height'];
    private const array SWITCHES = ['cod', 'lifting', 'elevator'];

    /** @return array<string, string|float|bool> */
    public static function fromPost(array $post): array
    {
        $input = [];
        foreach (self::TEXT as $name => $limit) {
            if (is_string($post[$name] ?? null)) {
                $input[$name] = mb_substr(trim($post[$name]), 0, $limit);
            }
        }
        foreach (self::NUMBERS as $name) {
            if (is_string($post[$name] ?? null)) {
                $raw = str_replace(',', '.', trim($post[$name]));
                // Не число лишається текстом, щоб чернетка повідомила про поле, а не зберегла старе значення.
                $input[$name] = is_numeric($raw) ? (float)$raw : mb_substr($raw, 0, 20);
            }
        }
        foreach (self::SWITCHES as $name) {
            if (is_string($post[$name] ?? null) && $post[$name] !== '' && $post[$name] !== '0') {
                $input[$name] = true;
            }
        }

        return $input;
    }
}
