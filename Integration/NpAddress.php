<?php

namespace Okay\Modules\Sviat\NovaPoshta\Integration;

/** Куди йде замовлення, простим текстом: відділення або вулична адреса. */
final readonly class NpAddress
{
    public function __construct(
        public string $city,
        public string $line,
    ) {
    }
}
