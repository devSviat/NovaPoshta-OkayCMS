<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** Адресна доставка як ціль розрахунку ціни: старий Ref міста населеного пункту й параметри поверху. */
final readonly class AddressTarget
{
    public function __construct(
        public int $settlementId,
        public string $cityRef,
        public bool $lifting,
        public ?int $floor,
        public bool $elevator,
        /** Кошик може пропонувати підйом на поверх: розрахувати й його, щоб показати додаткову вартість. */
        public bool $offerLifting,
    ) {
    }
}
