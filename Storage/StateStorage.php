<?php

namespace Okay\Modules\Sviat\NovaPoshta\Storage;

/** Невеликі іменовані значення, які модуль зберігає між запитами (JWT, результат перевірки ключа, версії довідників). */
interface StateStorage
{
    public function get(string $name): ?string;

    public function set(string $name, ?string $value): void;
}
