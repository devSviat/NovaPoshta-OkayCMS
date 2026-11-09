<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/**
 * Quotes and the customer's last choice, kept in the PHP session (`$bag` is the module's slot
 * in it). Plain arrays only: a session must not hold module objects that may change shape.
 */
final class QuoteSession
{
    private const int TTL = 3600;
    private const int MAX_QUOTES = 10;

    /**
     * @param ?int $retryUnreachableAfter seconds after which an "unreachable" answer counts as missing, so the
     *     cart asks Nova Poshta again; checkout passes null and keeps what the cart showed.
     */
    public static function get(array $bag, string $key, int $now, ?int $retryUnreachableAfter = null): ?DeliveryQuote
    {
        $row = $bag['quotes'][$key] ?? null;
        if (!is_array($row) || !is_int($row['at'] ?? null) || $now - $row['at'] >= self::TTL) {
            return null;
        }
        if ($retryUnreachableAfter !== null && ($row['problem'] ?? null) === 'unreachable' && $now - $row['at'] >= $retryUnreachableAfter) {
            return null;
        }
        $term = is_string($row['term'] ?? null) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $row['term']) : null;

        return new DeliveryQuote(
            is_numeric($row['price'] ?? null) ? (float)$row['price'] : null,
            $term ?: null,
            (string)($row['source'] ?? 'fallback'),
            is_string($row['problem'] ?? null) ? $row['problem'] : null,
            is_numeric($row['codFee'] ?? null) ? (float)$row['codFee'] : null,
            is_numeric($row['liftingFee'] ?? null) ? (float)$row['liftingFee'] : null,
            !empty($row['liftingRefused']),
        );
    }

    public static function put(array &$bag, string $key, DeliveryQuote $quote, int $now): void
    {
        $quotes = is_array($bag['quotes'] ?? null) ? $bag['quotes'] : [];
        unset($quotes[$key]);
        $quotes[$key] = ['price' => $quote->price, 'term' => $quote->term?->format('Y-m-d'), 'source' => $quote->source, 'problem' => $quote->problem, 'codFee' => $quote->codFee, 'liftingFee' => $quote->liftingFee, 'liftingRefused' => $quote->liftingRefused, 'at' => $now];
        $bag['quotes'] = array_slice($quotes, -self::MAX_QUOTES, null, true);
    }

    /** @param string $target "d:<division id>" or "a:<settlement id>" */
    public static function choose(array &$bag, int $deliveryId, string $target, bool $cod, bool $lifting = false, ?int $floor = null, bool $elevator = false): void
    {
        $chosen = is_array($bag['chosen'] ?? null) ? $bag['chosen'] : [];
        $chosen[$deliveryId] = ['target' => $target, 'cod' => $cod, 'lifting' => $lifting, 'floor' => $floor, 'elevator' => $elevator];
        $bag['chosen'] = $chosen;
    }

    /** @return array{target:string, cod:bool, lifting:bool, floor:?int, elevator:bool}|null */
    public static function chosen(array $bag, int $deliveryId): ?array
    {
        $row = is_array($bag['chosen'] ?? null) ? ($bag['chosen'][$deliveryId] ?? null) : null;
        if (!is_array($row) || !is_string($row['target'] ?? null)) {
            return null;
        }

        return [
            'target' => $row['target'],
            'cod' => (bool)($row['cod'] ?? false),
            'lifting' => (bool)($row['lifting'] ?? false),
            'floor' => is_int($row['floor'] ?? null) ? $row['floor'] : null,
            'elevator' => (bool)($row['elevator'] ?? false),
        ];
    }

    /** @return array{division_id?: int, settlement_id?: int} the ids a "d:" or "a:" target names */
    public static function targetIds(string $target): array
    {
        if (!preg_match('/^([da]):(\d+)$/', $target, $m) || (int)$m[2] <= 0) {
            return [];
        }

        return [$m[1] === 'd' ? 'division_id' : 'settlement_id' => (int)$m[2]];
    }
}
