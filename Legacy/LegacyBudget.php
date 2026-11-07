<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** How much one scheduler tick may spend: wall time and calls to Nova Poshta. */
final class LegacyBudget
{
    public function __construct(private readonly float $deadline, private int $calls)
    {
    }

    public static function forTick(float $seconds, int $calls): self
    {
        return new self(microtime(true) + $seconds, $calls);
    }

    public function hasTime(): bool
    {
        return microtime(true) < $this->deadline;
    }

    public function takeCall(): bool
    {
        if ($this->calls <= 0) {
            return false;
        }
        $this->calls--;

        return true;
    }
}
