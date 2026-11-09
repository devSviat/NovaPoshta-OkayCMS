<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Core\Money;
use Okay\Modules\Sviat\NovaPoshta\Storage\ModuleState;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\DeliveriesEntity;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;

/**
 * Розрахунок ціни для того, що відвідувач має в кошику саме зараз. Усі вхідні дані читаються на сервері:
 * спосіб доставки, пункт видачі й кошик — з бази даних і сесії, післяплата — з прапорця обраного
 * способу оплати. Нічого з того, що тут оцінюється, не береться з браузера.
 */
final class CartQuotes
{
    private const string SESSION_SLOT = 'sviat__novaposhta__quotes';
    private const int RETRY_UNREACHABLE_AFTER = 60;

    public function __construct(
        private readonly QuoteService $service,
        private readonly ShippingSettings $shipping,
        private readonly EntityFactory $entityFactory,
        private readonly CodPayments $codPayments,
        private readonly Module $module,
        private readonly Money $money,
        private readonly ModuleState $state,
        private readonly AddressCityResolver $addressCities,
    ) {
    }

    /**
     * @param bool $forOrder оформлення замовлення: один виклик на замовлення, уже саме по собі обмеження — без лімітів відвідувача
     * @return DeliveryQuote|null null, коли цей модуль не рахує ціну для цього способу, пункту видачі чи кошика
     */
    public function quote(object $delivery, int $divisionId, int $paymentId, object $cart, bool $forOrder = false, bool $forAdmin = false): ?DeliveryQuote
    {
        if (!$this->isOwnPointsMethod($delivery) || empty($cart->purchases)) {
            return null;
        }
        $division = $divisionId > 0 ? $this->entityFactory->get(DivisionEntity::class)->get($divisionId) : null;
        if (!$division || $division->status !== 'Working' || (int)$division->prohibited_issuance === 1) {
            return null;
        }

        return $this->run($delivery, 'd:' . $divisionId, $cart, $forOrder || $forAdmin, false, $forAdmin, null, false,
            static fn(ShippingConfig $config): array => [$division, null]);
    }

    /**
     * Адресна доставка: ціна й термін доставки до міста населеного пункту; якщо підйом на поверх пропонується
     * (налаштування адмінки), розраховується й додаткова вартість підйому.
     *
     * @return DeliveryQuote|null null, коли цей модуль не рахує ціну для цього способу чи кошика
     */
    public function quoteAddress(object $delivery, object $settlement, bool $lifting, ?int $floor, bool $elevator, int $paymentId, object $cart, bool $forOrder = false, bool $forAdmin = false): ?DeliveryQuote
    {
        if (!$this->isOwnMethod($delivery, DeliveryMode::Address) || empty($cart->purchases)) {
            return null;
        }
        $lifting = $lifting && $this->shipping->read()->showLifting;

        return $this->run($delivery, 'a:' . (int)$settlement->id, $cart, $forOrder || $forAdmin, $lifting, $forAdmin, $floor, $elevator,
            function (ShippingConfig $config) use ($settlement, $lifting, $floor, $elevator): array {
                try {
                    $cityRef = $this->addressCities->forSettlement($settlement);
                } catch (CityRefUnavailable) {
                    return [null, 'unreachable'];
                }
                if ($cityRef === null) {
                    return [null, 'no_city_ref'];
                }

                return [new AddressTarget((int)$settlement->id, $cityRef, $lifting, $floor, $elevator, $config->showLifting), null];
            });
    }

    /**
     * Спільна частина: вибір у сесії та кеш, ліміти, валюта, виклик і те, що зберігається.
     *
     * @param bool $forAdmin менеджер рахує ціну замовлення: ціну запитують, навіть коли вітрина її
     *     приховує, а кеш сесії відвідувача не читається й не записується
     * @param callable(ShippingConfig): array{0: object|AddressTarget|null, 1: ?string} $target отримувач або проблема
     */
    private function run(object $delivery, string $targetKey, object $cart, bool $forOrder, bool $lifting, bool $forAdmin, ?int $floor, bool $elevator, callable $target): DeliveryQuote
    {
        $config = $this->shipping->read();
        $sender = $config->senderDivisionId ? $this->entityFactory->get(DivisionEntity::class)->get($config->senderDivisionId) : null;
        if (!$sender) {
            return new DeliveryQuote(null, null, 'none', 'no_sender');
        }

        // Ціна не залежить від обраної оплати: комісія окремо від неї.
        $cod = $this->codFeeWanted($delivery);
        $bag = $forAdmin ? [] : $this->bag();
        if (!$forAdmin) {
            QuoteSession::choose($bag, (int)$delivery->id, $targetKey, $cod, $lifting, $floor, $elevator);
        }
        $key = QuoteKey::for((int)$delivery->id, $targetKey, $cod, $cart, $lifting, $floor, $elevator);
        $now = time();
        $cached = $forAdmin ? null : QuoteSession::get($bag, $key, $now, $forOrder ? null : self::RETRY_UNREACHABLE_AFTER);
        if ($cached !== null) {
            $this->store($bag);

            return $cached;
        }

        $wantPrice = $forAdmin || (empty($delivery->hide_front_delivery_price) && !FreeDelivery::applies($delivery, (float)($cart->total_price ?? 0)));
        $wantTerm = $config->showTerm;
        if (!$wantPrice && !$wantTerm) {
            $this->store($bag);

            return new DeliveryQuote(null, null, 'none', null);
        }
        if (!$forOrder && !QuoteThrottle::allow($bag, $now)) {
            $forAdmin || $this->store($bag);

            return new DeliveryQuote(null, null, 'fallback', 'busy');
        }
        $uah = $this->entityFactory->get(CurrenciesEntity::class)->findOne(['code' => 'UAH']);
        if (!$uah) {
            $forAdmin || $this->store($bag);

            return new DeliveryQuote(null, null, $wantPrice ? 'fallback' : 'none', 'no_uah');
        }
        if (!$forOrder && !$this->takeBudget($now)) {
            $forAdmin || $this->store($bag);

            return new DeliveryQuote(null, null, 'fallback', 'busy');
        }

        [$recipient, $problem] = $target($config);
        if ($recipient === null) {
            $quote = new DeliveryQuote(null, null, $wantPrice ? 'fallback' : 'none', $problem);
        } else {
            $settings = self::settings($delivery);
            $quote = $this->service->quote(new QuoteRequest(
                DeliveryMode::fromServiceType($settings['service_type'] ?? null)->serviceType(),
                $sender,
                $recipient instanceof AddressTarget ? null : $recipient,
                // Нова Пошта приймає оголошену вартість у гривнях; сума кошика — в основній валюті.
                Parcel::fromMetrics(CartMetrics::fromCart($cart), $config, (float)$this->money->convert((float)($cart->total_price ?? 0), (int)$uah->id, false, false, 2)),
                $cod,
                CodKind::Money,
                $wantPrice,
                $wantTerm,
                $recipient instanceof AddressTarget ? $recipient : null,
            ));
            $quote = $this->inMainCurrency($quote, $uah);
        }
        if ($forAdmin) {
            return $quote;
        }
        // Зберігається й тоді, коли НП недосяжна: оформлення бере те, що показав кошик, а не ціну НП хвилиною пізніше.
        QuoteSession::put($bag, $key, $quote, $now);
        $this->store($bag);

        return $quote;
    }

    /**
     * Свіжий розрахунок ціни для останнього вибору відвідувача в цьому способі й поточного кошика;
     * ніколи не викликає Нову Пошту.
     */
    public function cachedForChoice(object $delivery, object $cart): ?DeliveryQuote
    {
        $bag = $this->bag();
        $chosen = QuoteSession::chosen($bag, (int)$delivery->id);
        if ($chosen === null || !$this->isOwnMethod($delivery)) {
            return null;
        }

        return QuoteSession::get($bag, QuoteKey::for((int)$delivery->id, $chosen['target'], $chosen['cod'], $cart, $chosen['lifting'], $chosen['floor'], $chosen['elevator']), time());
    }

    /** @return array{target:string, cod:bool, lifting:bool, floor:?int, elevator:bool}|null що відвідувач востаннє обрав для цього способу */
    public function chosen(int $deliveryId): ?array
    {
        return QuoteSession::chosen($this->bag(), $deliveryId);
    }

    /**
     * Комісію запитують щоразу, коли спосіб можна оплатити післяплатою з грошовим переказом
     * (показується під цією оплатою).
     */
    private function codFeeWanted(object $delivery): bool
    {
        return $this->codFeePayments($delivery) !== [];
    }

    /**
     * Способи оплати цього способу доставки, під якими кошик показує комісію Нової Пошти:
     * позначені як його післяплата, і лише для грошового переказу (контроль оплати — витрата магазину).
     * @return list<int>
     */
    public function codFeePayments(object $delivery): array
    {
        $linked = array_map('intval', (array)$this->entityFactory->get(DeliveriesEntity::class)->getDeliveryPayments((int)$delivery->id));

        return CodPayments::wantsFee($linked, $moneyIds = $this->codPayments->moneyIds()) ? array_values(array_intersect($linked, $moneyIds)) : [];
    }

    public function isCodPayment(int $paymentId): bool
    {
        return $this->codPayments->isCod($paymentId);
    }

    public function isOwnPointsMethod(object $delivery): bool
    {
        return $this->isOwnMethod($delivery, DeliveryMode::Points);
    }

    public function isOwnMethod(object $delivery, ?DeliveryMode $mode = null): bool
    {
        $moduleId = (int)$this->module->getModuleIdByNamespace(__CLASS__);

        return $moduleId > 0 && (int)($delivery->module_id ?? 0) === $moduleId
            && ($mode === null || DeliveryMode::fromServiceType(self::settings($delivery)['service_type'] ?? null) === $mode);
    }

    /** Нова Пошта рахує ціни в гривнях; ціни доставки в OkayCMS — в основній валюті магазину. */
    private function inMainCurrency(DeliveryQuote $quote, object $uah): DeliveryQuote
    {
        if ($quote->price === null) {
            return $quote;
        }
        // Точність 2 явно: за замовчуванням Money округлює до `cents` валюти UAH, а це може бути 0.
        $convert = fn(float $amount): float => round((float)$this->money->convert($amount, (int)$uah->id, false, true, 2), 2);

        return new DeliveryQuote($convert($quote->price), $quote->term, $quote->source, $quote->problem, $quote->codFee === null ? null : $convert($quote->codFee), $quote->liftingFee === null ? null : $convert($quote->liftingFee), $quote->liftingRefused);
    }

    private function takeBudget(int $now, string $name = 'quote_budget', int $limit = QuoteBudget::LIMIT): bool
    {
        [$allowed, $state] = QuoteBudget::take($this->state->get($name), $now, $limit);
        $this->state->set($name, $state);

        return $allowed;
    }

    /**
     * Підказки вулиць запускаються на кожне натискання клавіші: у них власні ліміти, щоб введення
     * не витрачало ліміти розрахунку ціни.
     */
    public function allowStreetLookup(): bool
    {
        $bag = $this->bag();
        $now = time();
        $allowed = QuoteThrottle::allow($bag, $now, 'street_calls', 60) && $this->takeBudget($now, 'street_budget', 600);
        $this->store($bag);

        return $allowed;
    }

    private static function settings(object $delivery): array
    {
        $settings = $delivery->settings ?? [];
        if (is_string($settings)) {
            $settings = @unserialize($settings, ['allowed_classes' => false]);
        }

        return is_array($settings) ? $settings : [];
    }

    private function bag(): array
    {
        return is_array($_SESSION[self::SESSION_SLOT] ?? null) ? $_SESSION[self::SESSION_SLOT] : [];
    }

    private function store(array $bag): void
    {
        $_SESSION[self::SESSION_SLOT] = $bag;
    }
}
