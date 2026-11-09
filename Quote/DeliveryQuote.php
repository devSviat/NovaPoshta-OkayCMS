<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

final readonly class DeliveryQuote
{
    public function __construct(
        public ?float $price,
        public ?\DateTimeImmutable $term,
        /** new | old — який API розрахував ціну; none — ціну не запитували; fallback — запит не вдався. */
        public string $source,
        /** null | unreachable | rejected | no_city_ref | no_uah | busy | no_sender */
        public ?string $problem,
        /** Комісія Нової Пошти за переказ коштів післяплати: не входить у ціну, її платить отримувач у відділенні. */
        public ?float $codFee = null,
        /** Додаткова вартість підйому на поверх, коли він не безкоштовний (адресна доставка). */
        public ?float $liftingFee = null,
        /** Нова Пошта взагалі не піднімає цю посилку на поверх (напр. "Weight is too high for floors lifting"). */
        public bool $liftingRefused = false,
    ) {
    }

    /**
     * Нову Пошту запитали, і вона не дала ціни: з покупця не беруть плату за доставку, менеджер
     * повідомить її пізніше. "busy" сюди не належить: оформлення замовлення все одно рахує ціну.
     */
    public function unpriced(): bool
    {
        return $this->price === null && $this->source === 'fallback' && $this->problem !== 'busy';
    }
}
