<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/**
 * When a delivery method is free — the core's rule (DeliveriesHelper::getCartDeliveriesList and
 * prepareDeliveryPriceInfo), kept in one place so the cart and the admin never disagree.
 */
final class FreeDelivery
{
    /** Free unless free_from is above the goods total; 0 is therefore free for every cart. */
    public static function applies(object $delivery, float $goodsTotal): bool
    {
        return !((float)($delivery->free_from ?? 0) > $goodsTotal);
    }

    /**
     * A method marked paid whose threshold is 0: the core makes it free for every cart, so Nova
     * Poshta's price is never shown. Almost always a mistake; the settings page warns about it.
     */
    public static function freeForAll(object $delivery): bool
    {
        return !empty($delivery->paid) && (float)($delivery->free_from ?? 0) <= 0;
    }
}
