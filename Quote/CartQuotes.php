<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Core\Money;
use Okay\Entities\CurrenciesEntity;
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

    public function __construct(
        private readonly QuoteService $service,
        private readonly ShippingSettings $shipping,
        private readonly EntityFactory $entityFactory,
        private readonly CodPayments $codPayments,
        private readonly Module $module,
        private readonly Money $money,
    ) {
    }

    /** @return DeliveryQuote|null null when this module does not price this method or point */
    public function quote(object $delivery, int $divisionId, int $paymentId, object $cart): ?DeliveryQuote
    {
        if (!$this->isOwnPointsMethod($delivery)) {
            return null;
        }
        $division = $divisionId > 0 ? $this->entityFactory->get(DivisionEntity::class)->get($divisionId) : null;
        if (!$division || $division->status !== 'Working' || (int)$division->prohibited_issuance === 1) {
            return null;
        }

        $config = $this->shipping->read();
        $sender = $config->senderDivisionId ? $this->entityFactory->get(DivisionEntity::class)->get($config->senderDivisionId) : null;
        if (!$sender) {
            return new DeliveryQuote(null, null, 'none', 'no_sender');
        }

        $cod = $this->codPayments->isCod($paymentId);
        $bag = $this->bag();
        QuoteSession::choose($bag, (int)$delivery->id, $divisionId, $cod);
        $key = QuoteKey::for((int)$delivery->id, $divisionId, $cod, $cart);
        $now = time();
        $cached = QuoteSession::get($bag, $key, $now);
        if ($cached !== null) {
            $this->store($bag);

            return $cached;
        }

        $wantPrice = empty($delivery->hide_front_delivery_price) && (float)($delivery->free_from ?? 0) > (float)($cart->total_price ?? 0);
        $wantTerm = $config->showTerm;
        if (!$wantPrice && !$wantTerm) {
            $this->store($bag);

            return new DeliveryQuote(null, null, 'none', null);
        }
        if (!QuoteThrottle::allow($bag, $now)) {
            $this->store($bag);

            return new DeliveryQuote(null, null, 'fallback', 'busy');
        }

        $settings = self::settings($delivery);
        $quote = $this->service->quote(new QuoteRequest(
            (string)($settings['service_type'] ?? 'WarehouseWarehouse'),
            $sender,
            $division,
            Parcel::fromMetrics(CartMetrics::fromCart($cart), $config),
            $cod,
            $config->codKind,
            $wantPrice,
            $wantTerm,
        ));
        $quote = $this->inMainCurrency($quote);
        // An unreachable Nova Poshta is asked again next time instead of keeping the failure for an hour.
        if ($quote->problem !== 'unreachable') {
            QuoteSession::put($bag, $key, $quote, $now);
        }
        $this->store($bag);

        return $quote;
    }

    /** A fresh quote for the visitor's last choice on this method and the cart as it is now; never calls Nova Poshta. */
    public function cachedForChoice(object $delivery, object $cart): ?DeliveryQuote
    {
        $bag = $this->bag();
        $chosen = QuoteSession::chosen($bag, (int)$delivery->id);
        if ($chosen === null || !$this->isOwnPointsMethod($delivery)) {
            return null;
        }

        return QuoteSession::get($bag, QuoteKey::for((int)$delivery->id, $chosen['divisionId'], $chosen['cod'], $cart), time());
    }

    /** @return array{divisionId:int, cod:bool}|null what the visitor last chose for this method */
    public function chosen(int $deliveryId): ?array
    {
        return QuoteSession::chosen($this->bag(), $deliveryId);
    }

    public function isCodPayment(int $paymentId): bool
    {
        return $this->codPayments->isCod($paymentId);
    }

    public function isOwnPointsMethod(object $delivery): bool
    {
        $moduleId = (int)$this->module->getModuleIdByNamespace(__CLASS__);

        return $moduleId > 0 && (int)($delivery->module_id ?? 0) === $moduleId
            && DeliveryMode::fromServiceType(self::settings($delivery)['service_type'] ?? null) === DeliveryMode::Points;
    }

    /** Nova Poshta prices in hryvnias; delivery prices in OkayCMS are in the shop's main currency. */
    private function inMainCurrency(DeliveryQuote $quote): DeliveryQuote
    {
        if ($quote->price === null) {
            return $quote;
        }
        $uah = $this->entityFactory->get(CurrenciesEntity::class)->findOne(['code' => 'UAH']);
        if (!$uah) {
            return new DeliveryQuote(null, $quote->term, 'fallback', 'no_uah');
        }
        $convert = fn(float $amount): float => round((float)$this->money->convert($amount, (int)$uah->id, false, true), 2);

        return new DeliveryQuote($convert($quote->price), $quote->term, $quote->source, $quote->problem, $quote->codFee === null ? null : $convert($quote->codFee));
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
