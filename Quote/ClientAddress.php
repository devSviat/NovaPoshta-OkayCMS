<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** Адреса клієнта для лімітів: окремий клас, щоб ліміти можна було перевірити без суперглобальних змінних. */
class ClientAddress
{
    public function address(): ?string
    {
        $address = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($address) ? $address : null;
    }
}
