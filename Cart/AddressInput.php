<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/**
 * Reads the address fields one delivery method posted. Limits follow the old API's address note
 * (≤ 50 chars): entrance and note go there together, so they stay at 5 and 40.
 */
final class AddressInput
{
    private const array MAX = ['street' => 100, 'house' => 10, 'flat' => 10, 'entrance' => 5, 'note' => 40];
    private const int MAX_FLOOR = 200;

    /** @return array{address: ?AddressFields, error: ?string} error is a language key without the module prefix */
    public static function parse(array $post, int $deliveryId, bool $liftingOffered): array
    {
        $raw = is_array($post['novaposhta'] ?? null) && is_array($post['novaposhta'][$deliveryId] ?? null) ? $post['novaposhta'][$deliveryId] : [];
        $text = static fn(string $name): string => is_string($raw[$name] ?? null) ? trim(preg_replace('/[\p{Cc}\p{Cf}]+/u', '', $raw[$name]) ?? '') : '';
        $fail = static fn(string $error): array => ['address' => null, 'error' => $error];

        $settlementId = is_string($raw['settlement_id'] ?? null) && ctype_digit($raw['settlement_id']) ? (int)$raw['settlement_id'] : 0;
        if ($settlementId <= 0) {
            return $fail('error_city');
        }
        $values = [];
        foreach (array_keys(self::MAX) as $name) {
            $values[$name] = $text($name);
            if (preg_match('/[<>]/', $values[$name])) {
                return $fail('error_address_characters');
            }
        }
        if (mb_strlen($values['street']) < 2) {
            return $fail('error_street');
        }
        if ($values['house'] === '') {
            return $fail('error_house');
        }
        foreach (self::MAX as $name => $max) {
            if (mb_strlen($values[$name]) > $max) {
                return $fail('error_address_too_long');
            }
        }
        $floorText = $text('floor');
        $floor = null;
        if ($floorText !== '') {
            if (!ctype_digit($floorText) || (int)$floorText < 1 || (int)$floorText > self::MAX_FLOOR) {
                return $fail('error_floor');
            }
            $floor = (int)$floorText;
        }
        $lifting = $liftingOffered && !empty($raw['lifting']);
        if ($lifting && $floor === null) {
            return $fail('error_lifting_floor');
        }
        $ref = $text('street_ref');
        $empty = static fn(string $v): ?string => $v === '' ? null : $v;

        return ['address' => new AddressFields(
            $settlementId,
            $values['street'],
            preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $ref) ? $ref : null,
            $values['house'],
            $empty($values['flat']),
            $empty($values['entrance']),
            $floor,
            !empty($raw['elevator']),
            $empty($values['note']),
            $lifting,
        ), 'error' => null];
    }
}
