<?php

namespace Okay\Modules\Sviat\NovaPoshta\Storage;

/** Іменоване блокування між процесами: лише один тримає ім'я, решта отримують відмову. */
interface Mutex
{
    /** @param int $waitSeconds скільки чекати, доки інший процес відпустить ім'я; 0 — не чекати */
    public function acquire(string $name, int $waitSeconds = 0): bool;

    public function release(string $name): void;
}
