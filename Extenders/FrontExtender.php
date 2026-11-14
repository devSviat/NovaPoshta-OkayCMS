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
use Okay\Modules\Sviat\NovaPoshta\Cart\UahTotal;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartSelection;
use Okay\Modules\Sviat\NovaPoshta\Cart\AddressFields;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartSelectionValidator;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Cart\DivisionList;
use Okay\Modules\Sviat\NovaPoshta\Cart\PopularCities;
use Okay\Modules\Sviat\NovaPoshta\Cart\SettlementSearch;
use Okay\Modules\Sviat\NovaPoshta\Cart\ThemeClasses;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryLines;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryStore;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryView;
use Okay\Core\Money;
use Okay\Entities\CurrenciesEntity;
use Okay\Modules\Sviat\NovaPoshta\Quote\CartQuotes;
use Okay\Modules\Sviat\NovaPoshta\Quote\DeliveryPriceView;
use Okay\Modules\Sviat\NovaPoshta\Quote\QuoteSession;
use Okay\Modules\Sviat\NovaPoshta\Quote\FreeDelivery;
use Okay\Modules\Sviat\NovaPoshta\Quote\PriceDecision;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStore;

class FrontExtender implements ExtensionInterface
{
    /** Тексти, потрібні скрипту кошика; ключі design/lang/*.php без префікса. */
    private const array SCRIPT_TEXTS = [
        'cart_no_results', 'cart_choose', 'cart_shown_of', 'cart_all_day', 'cart_up_to_kg', 'cart_weekdays', 'cart_day_off', 'cart_change', 'cart_change_city', 'cart_schedule',
        'cart_too_heavy', 'cart_too_expensive', 'cart_dictionary_empty', 'cart_no_points', 'cart_search_failed', 'cart_no_city', 'cart_too_big',
        'cart_cod_fee', 'cart_cod_fee_unknown', 'cart_price_unreachable', 'cart_term', 'cart_calculating', 'cart_price_by_manager', 'cart_price_after_point', 'cart_price_after_address', 'cart_none_fit',
        'cart_street_failed', 'cart_no_street', 'cart_address_choice', 'cart_flat_short', 'cart_no_address', 'cart_lifting_paid', 'cart_lifting_unknown', 'cart_note_left', 'cart_price_rejected',
        'error_city', 'error_point', 'error_street', 'error_house', 'error_floor', 'error_lifting_floor',
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
        private readonly UahTotal $uahTotal,
        private readonly WaybillStore $waybills,
        private readonly OrderDeliveryLines $orderLines,
    ) {
    }

    /** Як розраховано ціну замовлення; зберігається від prepareDeliveryPriceInfo до знімка (той самий запит). */
    private ?array $pricing = null;

    /**
     * ChainExtension для DeliveriesHelper::getCartDeliveriesList: спосіб доставки з ціною, розрахованою
     * для пункту видачі відвідувача, показує цю ціну, також після того, як тема перезавантажить кошик через AJAX.
     */
    public function applyQuotes($deliveries, $cart)
    {
        if (!is_array($deliveries) || !is_object($cart)) {
            return $deliveries;
        }
        $dictionaryReady = null;
        foreach ($deliveries as $delivery) {
            if (!is_object($delivery)) {
                continue;
            }
            // Уже безкоштовна (free_from або безкоштовна доставка акції раніше в ланцюжку): лишається як є.
            $quote = empty($delivery->is_free_delivery) ? $this->quotes->cachedForChoice($delivery, $cart) : null;
            $own = $this->quotes->isOwnMethod($delivery);
            $total = (float)$cart->total_price;
            switch (DeliveryPriceView::cartState($delivery, $quote, $own, $own ? ($dictionaryReady ??= $this->settlements->hasAny()) : true, $total)) {
                case 'price':
                    $this->showPrice($delivery, $quote->price, $total);
                    break;
                case 'unpriced':
                    DeliveryPriceView::unpriced($delivery, $total, $this->translations->getTranslation('sviat__novaposhta__cart_price_by_manager'));
                    break;
                case 'free':
                    DeliveryPriceView::free($delivery, $total, $this->translations->getTranslation('cart_free'));
                    break;
                case 'awaiting':
                    $key = $this->quotes->isOwnPointsMethod($delivery) ? 'sviat__novaposhta__cart_price_after_point' : 'sviat__novaposhta__cart_price_after_address';
                    DeliveryPriceView::awaiting($delivery, $total, $this->translations->getTranslation($key));
                    break;
            }
        }

        return $deliveries;
    }

    /**
     * ChainExtension для DeliveriesHelper::prepareDeliveryPriceInfo: ціна доставки замовлення береться з розрахунку
     * на сервері.
     */
    public function orderPrice($info, $delivery, $order)
    {
        if (!is_array($info) || !is_object($delivery) || !is_object($order) || !$this->quotes->isOwnMethod($delivery)) {
            return $info;
        }
        $cart = $this->cart->get();
        $paymentId = (int)($order->payment_method_id ?? 0);
        $address = $this->selection?->address;
        if ($this->quotes->isOwnPointsMethod($delivery)) {
            $divisionId = $this->selection?->division ? (int)$this->selection->division->id : CartSelectionValidator::postedIds($_POST, (int)$delivery->id)[1];
            $quote = $this->quotes->quote($delivery, $divisionId, $paymentId, $cart, forOrder: true);
        } elseif ($address !== null) {
            $quote = $this->quotes->quoteAddress($delivery, $this->selection->settlement, $address->lifting, $address->floor, $address->elevator, $paymentId, $cart, forOrder: true);
            // Підйом на поверх, від якого Нова Пошта відмовляється (надто важко), не розраховано, тож у замовлення
            // він теж не входить.
            if ($address->lifting && $quote?->liftingRefused) {
                $this->selection = new CartSelection($this->selection->delivery, $this->selection->settlement, null, new AddressFields(
                    $address->settlementId, $address->street, $address->streetRef, $address->house, $address->flat,
                    $address->entrance, $address->floor, $address->elevator, $address->note, false,
                ));
            }
        } else {
            return $info;
        }
        $freeByCore = FreeDelivery::applies($delivery, (float)($order->total_price ?? 0));
        $decision = PriceDecision::forOrder($quote, $info, $freeByCore, $freeByCore ? 0.0 : (float)$delivery->price);
        $this->pricing = $quote === null ? null : [
            'delivery_price' => $decision['info']['delivery_price'] ?? null,
            'price_source' => $decision['source'],
            'delivery_term' => $quote->term?->format('Y-m-d'),
            'cod' => $this->quotes->isCodPayment((int)($order->payment_method_id ?? 0)) ? 1 : 0,
            // Зберігається й для замовлень з оплатою карткою: покупець може перейти на післяплату на сторінці
            // замовлення.
            'cod_fee' => $quote->codFee,
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

    /** Вибір, перевірений getCartValidateError; зберігається, щойно замовлення створено (той самий запит). */
    private ?CartSelection $selection = null;

    /** ChainExtension для ValidateHelper::getCartValidateError: форма кошика перевіряється на сервері. */
    public function getCartValidateError($error, $order)
    {
        if ($error !== null || !is_object($order)) {
            return $error;
        }
        $result = $this->validator->validate($order, $_POST, $this->uahTotal->metrics($this->cart->get()));
        if (is_string($result)) {
            return $this->translations->getTranslation('sviat__novaposhta__' . $result);
        }
        $this->selection = $result;

        return null;
    }

    /** QueueExtension для OrdersHelper::finalCreateOrderProcedure. */
    public function saveOrderDelivery($result, $order)
    {
        if (!is_object($order) || empty($order->id)) {
            return;
        }
        $selection = $this->selection ?? $this->validator->validate($order, $_POST, $this->uahTotal->metrics($this->cart->get()));
        if ($selection instanceof CartSelection) {
            $this->orders->save((int)$order->id, $selection, $this->pricing);
        }
    }

    /**
     * QueueExtension для OrdersHelper::getOrderPaymentMethodsList і NotifyHelper::finalEmailOrderUser/Admin:
     * сторінка замовлення й обидва листи показують збережений пункт видачі, поки доставка замовлення — цього модуля.
     * Для інших замовлень присвоюється null, бо один запит може рендерити листи кількох замовлень з тим самим Design.
     */
    public function assignOrderDelivery($result, $order)
    {
        $lines = is_object($order) ? $this->orderLines->forOrder($order) : null;
        if ($lines !== null) {
            $lines['waybill'] = OrderDeliveryView::waybill($this->waybills->forOrder((int)$order->id));
        }
        $this->design->assign('np_order_delivery', $lines);
        $this->design->assign('np_order_family', ThemeClasses::for((string)$this->settings->get('theme'))['family']);
    }

    /** Передає шаблону кошика те, що потрібно його блокам Нової Пошти; лише для способів доставки цього модуля. */
    public function getCartDeliveriesList($deliveries, $cart)
    {
        $moduleId = (int)$this->module->getModuleIdByNamespace(__CLASS__);
        $own = array_filter((array)$deliveries, static fn($d): bool => (int)($d->module_id ?? 0) === $moduleId);
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
        // JSON будується тут: Smarty 5 не дозволяє PHP-функції на кшталт json_encode як модифікатори.
        $this->design->assign('np_texts_json', json_encode($texts, JSON_UNESCAPED_UNICODE));
        $this->design->assign('np_modes', array_map(static fn($d): string => DeliveryMode::fromServiceType($d->settings['service_type'] ?? null) === DeliveryMode::Address ? 'address' : 'points', $own));
        $this->design->assign('np_prefill', $this->prefill($own, is_object($cart) ? $cart : (object)[], $lang));
        $this->design->assign('np_cod_payments', array_map(fn($d): string => implode(',', $this->quotes->codFeePayments($d)), $own));
    }

    /**
     * Вибір, який покупець уже зробив; показується знову, коли сторінка кошика повертається з помилкою.
     *
     * @param array<object> $deliveries способи доставки цього модуля
     * @return array<int, array{settlement:?array, division:?array}>
     */
    private function prefill(array $deliveries, object $cart, string $lang): array
    {
        $posted = $this->request->post('novaposhta');
        // Без надісланої форми попереднє замовлення покупця знову пропонує свій пункт видачі.
        $user = $this->design->getVar('user');
        $last = !is_array($posted) && is_object($user) ? $this->orders->lastForUser((int)($user->id ?? 0)) : null;
        $prefill = [];
        $metrics = CartMetrics::fromCart($cart);
        foreach ($deliveries as $delivery) {
            $deliveryId = (int)$delivery->id;
            $choice = self::prefillChoice(is_array($posted[$deliveryId] ?? null) ? $posted[$deliveryId] : null, $this->sessionChoice($deliveryId), $last ?: null);
            $described = $this->describe($delivery, $metrics, (int)($choice['settlement_id'] ?? 0), (int)($choice['division_id'] ?? 0), $lang);
            $prefill[(int)$deliveryId] = $described + [
                'address' => self::addressPrefill($choice),
                'settlement_json' => $described['settlement'] ? json_encode($described['settlement'], JSON_UNESCAPED_UNICODE) : '',
                'division_json' => $described['division'] ? json_encode($described['division'], JSON_UNESCAPED_UNICODE) : '',
            ];
        }

        return $prefill;
    }

    /**
     * Надіслана форма, інакше вибір, розрахований у цій сесії, інакше останнє замовлення. Для адреси сесія
     * зберігає лише місто, тож у місті останнього замовлення повертаються також його вулиця й будинок.
     */
    public static function prefillChoice(?array $posted, array $session, ?object $last): array
    {
        if ($posted !== null) {
            return $posted;
        }
        if ($session !== []) {
            $sameCity = $last !== null && !isset($session['division_id']) && (int)($session['settlement_id'] ?? 0) === (int)($last->settlement_id ?? 0);

            return $sameCity ? $session + self::lastAddress($last) : $session;
        }

        return $last ? ['settlement_id' => $last->settlement_id, 'division_id' => $last->division_id] + self::lastAddress($last) : [];
    }

    /** @return array{settlement_id?: int, division_id?: int} */
    private function sessionChoice(int $deliveryId): array
    {
        $ids = QuoteSession::targetIds((string)($this->quotes->chosen($deliveryId)['target'] ?? ''));
        if (isset($ids['division_id'])) {
            $division = $this->entityFactory->get(DivisionEntity::class)->get($ids['division_id']);
            $ids['settlement_id'] = $division ? (int)$division->settlement_id : 0;
        }

        return $ids;
    }

    /** Адресні поля знімка попереднього замовлення під іменами, які надсилає кошик. */
    private static function lastAddress(object $last): array
    {
        if (($last->mode ?? null) !== 'address') {
            return [];
        }

        return array_filter([
            'street' => $last->street_name ?? null,
            'street_ref' => $last->street_ref ?? null,
            'house' => $last->house ?? null,
            'flat' => $last->flat ?? null,
            'entrance' => $last->entrance ?? null,
            'floor' => $last->floor ?? null,
            'elevator' => $last->elevator ?? null,
            'note' => $last->address_note ?? null,
        ], static fn($v): bool => $v !== null && $v !== '');
    }

    /** @return array<string, string> у форму повертаються лише прості рядки; шаблон їх екранує */
    private static function addressPrefill(array $choice): array
    {
        $out = [];
        foreach (['street', 'street_ref', 'house', 'flat', 'entrance', 'floor', 'elevator', 'note', 'lifting'] as $name) {
            if (is_scalar($choice[$name] ?? null)) {
                $out[$name] = mb_substr((string)$choice[$name], 0, 100);
            }
        }

        return $out;
    }

    /** @return array{settlement:?array{id:int, name:string, area:?string}, division:?array{id:int, title:string, place:?string, schedule:mixed}} */
    public function describe(object $delivery, CartMetrics $cart, int $settlementId, int $divisionId, string $lang): array
    {
        $english = $lang === 'en';
        $settlement = $settlementId > 0 ? $this->entityFactory->get(SettlementEntity::class)->get($settlementId) : null;
        $division = $settlement && $divisionId > 0 ? $this->entityFactory->get(DivisionEntity::class)->get($divisionId) : null;
        // Пункт видачі, який замовлення зараз відхилило б, повертається до списку, де показано його проблему.
        $division = $division ? $this->validator->restorable($delivery, $settlement, $division, $cart) : null;

        return [
            'settlement' => $settlement ? self::settlementView($settlement, $english) : null,
            'division' => $division ? ['id' => (int)$division->id] + DivisionList::describe($division, $english) : null,
        ];
    }

    /** @return array{id: int, name: string, type: string, title: string, district: ?string, area: ?string} */
    private static function settlementView(object $settlement, bool $english): array
    {
        $name = (string)($english && $settlement->name_en ? $settlement->name_en : $settlement->name_uk);
        [$type, $title] = \Okay\Modules\Sviat\NovaPoshta\Cart\SettlementName::split($name);

        return [
            'id' => (int)$settlement->id,
            'name' => $name,
            'type' => $type,
            'title' => $title,
            'district' => (string)($english && $settlement->district_en ? $settlement->district_en : $settlement->district_uk) ?: null,
            'area' => (string)($english && $settlement->area_en ? $settlement->area_en : $settlement->area_uk) ?: null,
        ];
    }
}
