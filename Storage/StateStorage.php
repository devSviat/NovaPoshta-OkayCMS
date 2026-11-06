<?php

namespace Okay\Modules\Sviat\NovaPoshta\Storage;

/** Small named values the module keeps between requests (JWT, key check result, dictionary versions). */
interface StateStorage
{
    public function get(string $name): ?string;

    public function set(string $name, ?string $value): void;
}
