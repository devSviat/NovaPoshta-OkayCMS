<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Modules\Sviat\NovaPoshta\Storage\Mutex;

/** Блокування в пам'яті: `held` імітує інший процес, що тримає ім'я. */
final class InMemoryMutex implements Mutex
{
    /** @var array<string, true> */
    public array $held = [];

    public function acquire(string $name, int $waitSeconds = 0): bool
    {
        if (isset($this->held[$name])) {
            return false;
        }
        $this->held[$name] = true;

        return true;
    }

    public function release(string $name): void
    {
        unset($this->held[$name]);
    }
}
