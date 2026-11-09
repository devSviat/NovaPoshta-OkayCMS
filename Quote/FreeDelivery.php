<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/**
 * Коли спосіб доставки безкоштовний — правило ядра (DeliveriesHelper::getCartDeliveriesList і
 * prepareDeliveryPriceInfo), зібране в одному місці, щоб кошик і адмінка ніколи не розходилися.
 */
final class FreeDelivery
{
    /** Безкоштовно, якщо тільки free_from не більший за суму товарів; тож 0 — безкоштовно для будь-якого кошика. */
    public static function applies(object $delivery, float $goodsTotal): bool
    {
        return !((float)($delivery->free_from ?? 0) > $goodsTotal);
    }

    /**
     * Спосіб, позначений платним, з порогом 0: ядро робить його безкоштовним для будь-якого кошика, тож ціна
     * Нової Пошти ніколи не показується. Майже завжди це помилка; сторінка налаштувань про неї попереджає.
     */
    public static function freeForAll(object $delivery): bool
    {
        return !empty($delivery->paid) && (float)($delivery->free_from ?? 0) <= 0;
    }
}
