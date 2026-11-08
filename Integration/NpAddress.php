<?php

namespace Okay\Modules\Sviat\NovaPoshta\Integration;

/** Where an order goes, as plain text: a branch or a street address. */
final readonly class NpAddress
{
    public function __construct(
        public string $city,
        public string $line,
    ) {
    }
}
