<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

/** The answer to the manager's change of an order's point or address. */
final readonly class OrderPointResult
{
    /**
     * @param ?string $error language key (without the module prefix) of why nothing was saved
     * @param ?string $warning language key of what the manager should know about a saved point
     * @param ?array{action: string, price: ?float} $price what happened to the delivery price
     */
    public function __construct(
        public bool $ok,
        public ?string $error = null,
        public ?string $warning = null,
        public ?array $price = null,
    ) {
    }

    public static function failed(string $error): self
    {
        return new self(false, $error);
    }
}
