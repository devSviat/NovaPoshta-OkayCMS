<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/** Перевірений пункт видачі чи адреса для одного замовлення або нічого, коли доставка не цього модуля. */
final readonly class CartSelection
{
    public function __construct(
        public ?object $delivery,
        public ?object $settlement,
        public ?object $division,
        public ?AddressFields $address = null,
    ) {
    }

    public static function none(): self
    {
        return new self(null, null, null);
    }

    public function applies(): bool
    {
        return $this->settlement !== null && ($this->division !== null || $this->address !== null);
    }
}
