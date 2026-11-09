<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Modules\Sviat\NovaPoshta\Storage\StateStorage;

final class InMemoryState implements StateStorage
{
    /** @var array<string, string> */
    public array $values = [];

    public function get(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }

    public function set(string $name, ?string $value): void
    {
        if ($value === null) {
            unset($this->values[$name]);
        } else {
            $this->values[$name] = $value;
        }
    }
}
