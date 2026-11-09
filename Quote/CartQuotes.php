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
 * The quote for what the visitor has in the cart right now. Every input is read on the server:
 * the delivery method, the point and the cart from the database and the session, cash on
 * delivery from the chosen payment method's flag. Nothing priced here is taken from the browser.
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
     * @param bool $forOrder checkout: one call per order, already a gate — no visitor limits
     * @return DeliveryQuote|null null when this module does not price this method, point or cart
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
     * Address delivery: price and term to the settlement's city; with lifting offered (admin
     * setting) the extra cost of floor lifting is priced too.
     *
     * @return DeliveryQuote|null null when this module does not price this method or cart
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
     * The common part: session choice and cache, limits, currency, the call and what is kept.
     *
     * @param bool $forAdmin a manager prices an order: the price is asked even when the storefront hides
     *     it, and the visitor's session cache is neither read nor written
     * @param callable(ShippingConfig): array{0: object|AddressTarget|null, 1: ?string} $target recipient, or the problem
     */
    private function run(object $delivery, string $targetKey, object $cart, bool $forOrder, bool $lifting, bool $forAdmin, ?int $floor, bool $elevator, callable $target): DeliveryQuote
    {
        $config = $this->shipping->read();
        $sender = $config->senderDivisionId ? $this->entityFactory->get(DivisionEntity::class)->get($config->senderDivisionId) : null;
        if (!$sender) {
            return new DeliveryQuote(null, null, 'none', 'no_sender');
        }

        // The price does not depend on the chosen payment: the commission is apart from it.
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
                // Nova Poshta takes the declared value in hryvnias; the cart total is in the main currency.
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
        // Kept even when unreachable: checkout must charge what the cart showed, not a price NP gives a minute later.
        QuoteSession::put($bag, $key, $quote, $now);
        $this->store($bag);

        return $quote;
    }

    /** A fresh quote for the visitor's last choice on this method and the cart as it is now; never calls Nova Poshta. */
    public function cachedForChoice(object $delivery, object $cart): ?DeliveryQuote
    {
        $bag = $this->bag();
        $chosen = QuoteSession::chosen($bag, (int)$delivery->id);
        if ($chosen === null || !$this->isOwnMethod($delivery)) {
            return null;
        }

        return QuoteSession::get($bag, QuoteKey::for((int)$delivery->id, $chosen['target'], $chosen['cod'], $cart, $chosen['lifting'], $chosen['floor'], $chosen['elevator']), time());
    }

    /** @return array{target:string, cod:bool, lifting:bool, floor:?int, elevator:bool}|null what the visitor last chose for this method */
    public function chosen(int $deliveryId): ?array
    {
        return QuoteSession::chosen($this->bag(), $deliveryId);
    }

    /** The commission is asked whenever the method can be paid by money-transfer COD (shown under that payment). */
    private function codFeeWanted(object $delivery): bool
    {
        return $this->codFeePayments($delivery) !== [];
    }

    /**
     * Payment methods of this delivery method under which the cart shows Nova Poshta's commission:
     * marked as its cash on delivery, and only for a money transfer (payment control is the shop's cost).
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

    /** Nova Poshta prices in hryvnias; delivery prices in OkayCMS are in the shop's main currency. */
    private function inMainCurrency(DeliveryQuote $quote, object $uah): DeliveryQuote
    {
        if ($quote->price === null) {
            return $quote;
        }
        // Precision 2 explicitly: by default Money rounds to the UAH currency's `cents`, which may be 0.
        $convert = fn(float $amount): float => round((float)$this->money->convert($amount, (int)$uah->id, false, true, 2), 2);

        return new DeliveryQuote($convert($quote->price), $quote->term, $quote->source, $quote->problem, $quote->codFee === null ? null : $convert($quote->codFee), $quote->liftingFee === null ? null : $convert($quote->liftingFee), $quote->liftingRefused);
    }

    private function takeBudget(int $now, string $name = 'quote_budget', int $limit = QuoteBudget::LIMIT): bool
    {
        [$allowed, $state] = QuoteBudget::take($this->state->get($name), $now, $limit);
        $this->state->set($name, $state);

        return $allowed;
    }

    /** Street suggestions run per keystroke: limits of their own, so typing does not spend the price ones. */
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
