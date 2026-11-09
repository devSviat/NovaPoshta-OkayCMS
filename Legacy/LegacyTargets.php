<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Куди йдуть дрібні частини старих даних: поля оплат і варіантів, налаштування. */
interface LegacyTargets
{
    /** @return object|null id, module, cod (наш прапорець CodPayments) */
    public function payment(int $id): ?object;

    public function setPaymentCod(int $id, int $flag): void;

    /** @return float|null наш об'єм варіанта; null, коли варіанта вже немає */
    public function variantVolume(int $id): ?float;

    public function setVariantVolume(int $id, float $volume): void;

    public function ourSetting(string $name): ?string;

    public function setOurSetting(string $name, string $value): void;
}
