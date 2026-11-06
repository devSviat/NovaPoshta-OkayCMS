<?php

namespace Okay\Modules\Sviat\NovaPoshta\Extenders;

use Okay\Core\Cart;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\FrontTranslations;
use Okay\Core\Languages;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Modules\Module;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartSelection;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartSelectionValidator;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Cart\PopularCities;
use Okay\Modules\Sviat\NovaPoshta\Cart\SettlementSearch;
use Okay\Modules\Sviat\NovaPoshta\Cart\ThemeClasses;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryStore;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryView;
use Okay\Core\Money;
use Okay\Entities\CurrenciesEntity;
use Okay\Modules\Sviat\NovaPoshta\Quote\CartQuotes;
use Okay\Modules\Sviat\NovaPoshta\Quote\DeliveryPriceView;
use Okay\Modules\Sviat\NovaPoshta\Quote\PriceDecision;

class FrontExtender implements ExtensionInterface
{
    /** Texts the cart script needs; keys of design/lang/*.php without the prefix. */
    private const array SCRIPT_TEXTS = [
        'cart_no_results', 'cart_choose', 'cart_shown_of', 'cart_closes', 'cart_all_day', 'cart_up_to_kg',
        'cart_too_heavy', 'cart_too_expensive', 'cart_choice', 'cart_dictionary_empty', 'cart_no_points', 'cart_search_failed', 'cart_no_city', 'cart_too_big',
        'cart_cod_fee', 'cart_price_unreachable', 'cart_term', 'cart_calculating', 'cart_price_by_manager',
    ];

    public function __construct(
        private readonly Design $design,
        private readonly Settings $settings,
        private readonly Module $module,
        private readonly Request $request,
        private readonly PopularCities $popularCities,
        private readonly Languages $languages,
        private readonly EntityFactory $entityFactory,
        private readonly FrontTranslations $translations,
        private readonly CartSelectionValidator $validator,
        private readonly OrderDeliveryStore $orders,
        private readonly Cart $cart,
        private readonly SettlementSearch $settlements,
        private readonly CartQuotes $quotes,
        private readonly Money $money,
    ) {
    }

    /** How the order was priced, kept from prepareDeliveryPriceInfo to the snapshot (same request). */
    private ?array $pricing = null;

    /**
     * ChainExtension of DeliveriesHelper::getCartDeliveriesList: a method priced for the visitor's
     * point shows that price, also after the theme reloads the cart by AJAX.
     */
    public function applyQuotes($deliveries, $cart)
    {
        if (!is_array($deliveries) || !is_object($cart)) {
            return $deliveries;
        }
        foreach ($deliveries as $delivery) {
            $quote = is_object($delivery) ? $this->quotes->cachedForChoice($delivery, $cart) : null;
            if ($quote?->price !== null) {
                $this->showPrice($delivery, $quote->price, (float)$cart->total_price);
            }
        }

        return $deliveries;
    }

    /** ChainExtension of DeliveriesHelper::prepareDeliveryPriceInfo: the order's delivery price comes from the server's calculation. */
    public function orderPrice($info, $delivery, $order)
    {
        if (!is_array($info) || !is_object($delivery) || !is_object($order) || !$this->quotes->isOwnPointsMethod($delivery)) {
            return $info;
        }
        $divisionId = $this->selection?->applies() ? (int)$this->selection->division->id : CartSelectionValidator::postedIds($_POST, (int)$delivery->id)[1];
        $cart = $this->cart->get();
        $quote = $this->quotes->quote($delivery, $divisionId, (int)($order->payment_method_id ?? 0), $cart);
        $freeByCore = !((float)($delivery->free_from ?? 0) > (float)($order->total_price ?? 0));
        $decision = PriceDecision::forOrder($quote, $info, $freeByCore);
        $this->pricing = $quote === null ? null : [
            'delivery_price' => $decision['info']['delivery_price'] ?? null,
            'price_source' => $decision['source'],
            'delivery_term' => $quote->term?->format('Y-m-d'),
            'cod' => $this->quotes->isCodPayment((int)($order->payment_method_id ?? 0)) ? 1 : 0,
        ];

        return $decision['info'];
    }

    private function showPrice(object $delivery, float $price, float $cartTotal): void
    {
        $currencies = $this->entityFactory->get(CurrenciesEntity::class);
        $currency = $currencies->get((int)($_SESSION['currency_id'] ?? 0)) ?: $currencies->getMainCurrency();
        DeliveryPriceView::apply(
            $delivery,
            $price,
            $cartTotal,
            fn(float $amount): string => (string)$this->money->convert($amount),
            (string)($currency->sign ?? ''),
            $this->translations->getTranslation('cart_paid_separate'),
            $this->translations->getTranslation('cart_free'),
        );
    }

    /** The choice checked by getCartValidateError, saved once the order exists (same request). */
    private ?CartSelection $selection = null;

    /** ChainExtension of ValidateHelper::getCartValidateError: the cart form is checked on the server. */
    public function getCartValidateError($error, $order)
    {
        if ($error !== null || !is_object($order)) {
            return $error;
        }
        $result = $this->validator->validate($order, $_POST, CartMetrics::fromCart($this->cart->get()));
        if (is_string($result)) {
            return $this->translations->getTranslation('sviat__novaposhta__' . $result);
        }
        $this->selection = $result;

        return null;
    }

    /** QueueExtension of OrdersHelper::finalCreateOrderProcedure. */
    public function saveOrderDelivery($result, $order)
    {
        if (!is_object($order) || empty($order->id)) {
            return;
        }
        $selection = $this->selection ?? $this->validator->validate($order, $_POST, CartMetrics::fromCart($this->cart->get()));
        if ($selection instanceof CartSelection) {
            $this->orders->save((int)$order->id, $selection, $this->pricing);
        }
    }

    /**
     * QueueExtension of OrdersHelper::getOrderPaymentMethodsList and NotifyHelper::finalEmailOrderUser/Admin:
     * the order page and both emails show the saved point. Assigned as null for other orders, because
     * one request can render several orders' emails with the same Design.
     */
    public function assignOrderDelivery($result, $order)
    {
        $row = is_object($order) && !empty($order->id) ? $this->orders->forOrder((int)$order->id) : null;
        $this->design->assign('np_order_delivery', $row ? OrderDeliveryView::lines($row) : null);
        $this->design->assign('np_order_family', ThemeClasses::for((string)$this->settings->get('theme'))['family']);
    }

    /** Hands the cart template what its Nova Poshta blocks need; only for this module's delivery methods. */
    public function getCartDeliveriesList($deliveries, $cart)
    {
        $moduleId = (int)$this->module->getModuleIdByNamespace(__CLASS__);
        $own = array_filter((array)$deliveries, static fn($d): bool => (int)($d->module_id ?? 0) === $moduleId
            && DeliveryMode::fromServiceType($d->settings['service_type'] ?? null) === DeliveryMode::Points);
        if ($own === []) {
            return;
        }

        $lang = (string)$this->languages->getLangLabel();
        $texts = [];
        foreach (self::SCRIPT_TEXTS as $key) {
            $texts[$key] = $this->translations->getTranslation('sviat__novaposhta__' . $key);
        }

        $this->design->assign('np_module_id', $moduleId);
        $this->design->assign('np_unavailable', !$this->settlements->hasAny());
        $this->design->assign('np_theme', ThemeClasses::for((string)$this->settings->get('theme')));
        $this->design->assign('np_popular', $this->popularCities->buttons($lang));
        // JSON is built here: Smarty 5 does not allow PHP functions such as json_encode as modifiers.
        $this->design->assign('np_texts_json', json_encode($texts, JSON_UNESCAPED_UNICODE));
        $this->design->assign('np_prefill', $this->prefill(array_keys($own), $lang));
    }

    /**
     * The choice the customer already made, shown again when the cart page comes back with an error.
     *
     * @param list<int|string> $deliveryIds
     * @return array<int, array{settlement:?array, division:?array}>
     */
    private function prefill(array $deliveryIds, string $lang): array
    {
        $posted = $this->request->post('novaposhta');
        // Without a posted form, the customer's previous order offers its point again.
        $user = $this->design->getVar('user');
        $last = !is_array($posted) && is_object($user) ? $this->orders->lastForUser((int)($user->id ?? 0)) : null;
        $prefill = [];
        foreach ($deliveryIds as $deliveryId) {
            $choice = is_array($posted[$deliveryId] ?? null) ? $posted[$deliveryId]
                : ($last ? ['settlement_id' => $last->settlement_id, 'division_id' => $last->division_id] : []);
            $described = $this->describe((int)($choice['settlement_id'] ?? 0), (int)($choice['division_id'] ?? 0), $lang);
            $prefill[(int)$deliveryId] = $described + [
                'settlement_json' => $described['settlement'] ? json_encode($described['settlement'], JSON_UNESCAPED_UNICODE) : '',
                'division_json' => $described['division'] ? json_encode($described['division'], JSON_UNESCAPED_UNICODE) : '',
            ];
        }

        return $prefill;
    }

    /** @return array{settlement:?array{id:int, name:string}, division:?array{id:int, title:string}} */
    public function describe(int $settlementId, int $divisionId, string $lang): array
    {
        $english = $lang === 'en';
        $settlement = $settlementId > 0 ? $this->entityFactory->get(SettlementEntity::class)->get($settlementId) : null;
        $division = $settlement && $divisionId > 0 ? $this->entityFactory->get(DivisionEntity::class)->get($divisionId) : null;
        if ($division && (int)$division->settlement_id !== (int)$settlement->id) {
            $division = null;
        }

        return [
            'settlement' => $settlement ? ['id' => (int)$settlement->id, 'name' => (string)($english && $settlement->name_en ? $settlement->name_en : $settlement->name_uk)] : null,
            'division' => $division ? ['id' => (int)$division->id, 'title' => (string)($english && $division->name_en ? $division->name_en : $division->name_uk)] : null,
        ];
    }
}
