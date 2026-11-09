<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume;

/** Визначає, для чого зроблено розрахунок ціни; будь-яка зміна кошика дає новий ключ, тож застаріла ціна не показується. */
final class QuoteKey
{
    /** Збільшується, коли змінюється зміст закешованого розрахунку ціни. */
    private const string FORMAT = 'v4';

    /**
     * @param string $target "d:<division id>" або "a:<settlement id>"
     * @param ?int $floor разом з $elevator входить у ключ лише з підйомом на поверх: НП рахує підйом за обома
     */
    public static function for(int $deliveryId, string $target, bool $cod, object $cart, bool $lifting = false, ?int $floor = null, bool $elevator = false): string
    {
        $lines = [];
        foreach ((array)($cart->purchases ?? []) as $purchase) {
            // Вага й об'єм варіанта: адміністратор може змінити їх, поки ціна лежить у кеші сесії.
            $variant = $purchase->variant ?? null;
            $lines[] = (int)($purchase->variant_id ?? $variant->id ?? 0) . ':' . (int)($purchase->amount ?? 0)
                . ':' . (float)($variant->weight ?? 0) . ':' . (float)($variant->{VariantVolume::FIELD} ?? 0);
        }
        sort($lines);

        $lift = $lifting ? '1:' . (int)$floor . ':' . ($elevator ? 1 : 0) : '0';

        return sha1(implode('|', [self::FORMAT, $deliveryId, $target, $cod ? 1 : 0, $lift, implode(',', $lines), number_format((float)($cart->total_price ?? 0), 2, '.', '')]));
    }
}
