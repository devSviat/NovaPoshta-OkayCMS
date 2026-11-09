<?php

namespace Okay\Modules\Sviat\NovaPoshta\Controllers;

use Okay\Core\Cart;
use Okay\Core\Languages;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Cart\CrossSiteRequest;
use Okay\Modules\Sviat\NovaPoshta\Cart\DivisionList;
use Okay\Modules\Sviat\NovaPoshta\Cart\UahTotal;
use Okay\Modules\Sviat\NovaPoshta\Cart\SettlementSearch;
use Okay\Core\EntityFactory;
use Okay\Helpers\DeliveriesHelper;
use Okay\Helpers\UserHelper;
use Okay\Entities\PaymentsEntity;
use Okay\Core\Money;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\DeliveriesEntity;
use Okay\Modules\Sviat\NovaPoshta\Quote\CartQuotes;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Cart\StreetSearch;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Core\FrontTranslations;
use Okay\Modules\Sviat\NovaPoshta\Quote\DeliveryPriceView;

/**
 * Публічні endpoints кошика. Вони читають лише довідник Нової Пошти і власний кошик
 * відвідувача: жодних замовлень, жодних персональних даних.
 */
class CartController
{
    public function settlements(Request $request, Response $response, SettlementSearch $search, Languages $languages)
    {
        $query = self::typed($request);
        $pickupOnly = $request->get('mode') !== 'address';
        $this->json($response, ['settlements' => is_string($query) ? $search->find($query, (string)$languages->getLangLabel(), 10, $pickupOnly) : []]);
    }

    public function divisions(Request $request, Response $response, DivisionList $divisions, Cart $cart, UserHelper $userHelper, Languages $languages, UahTotal $uahTotal)
    {
        $settlementId = (int)$request->get('settlement_id', 'integer');
        if ($settlementId <= 0) {
            $response->setStatusCode(400);
            $this->json($response, ['error' => 'settlement_id']);

            return;
        }
        $groupId = $request->get('group_id', 'integer');
        $this->json($response, $divisions->forSettlement(
            $settlementId,
            $uahTotal->metrics(self::visitorCart($cart, $userHelper)),
            (string)$languages->getLangLabel(),
            $groupId ? (int)$groupId : null,
            is_string($query = self::typed($request)) ? $query : '',
        ));
    }

    /**
     * Ціна й термін для обраного пункту видачі (`ajax/novaposhta/quote?delivery_id=&division_id=&payment_id=`).
     * Числа способу доставки беруться з власного списку кошика ядра, тож кожен модуль, що його розширює
     * (безкоштовна доставка промо), формує відповідь так само, як формує сторінку.
     */
    public function quote(Request $request, Response $response, CartQuotes $quotes, Cart $cart, UserHelper $userHelper, EntityFactory $entityFactory, Money $money, DeliveriesHelper $deliveriesHelper, ShippingSettings $shipping, FrontTranslations $translations)
    {
        if (CrossSiteRequest::is($_SERVER)) {
            $response->setStatusCode(403);
            $this->json($response, ['error' => 'origin']);

            return;
        }
        $deliveryId = (int)$request->get('delivery_id', 'integer');
        $delivery = $deliveryId > 0 ? $entityFactory->get(DeliveriesEntity::class)->get($deliveryId) : null;
        $cartData = self::visitorCart($cart, $userHelper);
        $paymentId = (int)$request->get('payment_id', 'integer');
        $quote = null;
        if ($delivery && !empty($delivery->enabled)) {
            if ($request->get('mode') === 'address') {
                $settlementId = (int)$request->get('settlement_id', 'integer');
                $settlement = $settlementId > 0 ? $entityFactory->get(SettlementEntity::class)->get($settlementId) : null;
                $floor = (int)$request->get('floor', 'integer');
                $quote = $settlement ? $quotes->quoteAddress($delivery, $settlement, (bool)$request->get('lifting'), $floor >= 1 && $floor <= 200 ? $floor : null, (bool)$request->get('elevator'), $paymentId, $cartData) : null;
            } else {
                $quote = $quotes->quote($delivery, (int)$request->get('division_id', 'integer'), $paymentId, $cartData);
            }
        }
        if ($quote === null) {
            $response->setStatusCode(400);
            $this->json($response, ['error' => 'input']);

            return;
        }
        if ($quote->problem === 'busy') {
            $response->setStatusCode(429);
        }

        $answer = ['price' => null, 'unpriced' => false, 'priceText' => null, 'totalWithDelivery' => null, 'free' => false, 'codFee' => null, 'codFeeText' => null, 'term' => $quote->term?->format('Y-m-d'), 'problem' => $quote->problem, 'liftingOffered' => false, 'liftingFeeText' => null];
        // Підйом пропонується лише тоді, коли він коштує додатково, або коли вага якихось товарів невідома.
        if ($request->get('mode') === 'address' && $shipping->read()->showLifting && !$quote->liftingRefused
            && ($quote->liftingFee !== null || CartMetrics::fromCart($cartData)->weightUnknown)) {
            $answer['liftingOffered'] = true;
            if ($quote->liftingFee !== null) {
                $answer['liftingFeeText'] = '+' . $money->convert($quote->liftingFee) . ' ' . $this->currencySign($entityFactory);
            }
        }
        $payments = $entityFactory->get(PaymentsEntity::class)->mappedBy('id')->find(['enabled' => 1]);
        $item = $deliveriesHelper->getCartDeliveriesList($cartData, $payments)[$deliveryId] ?? null;
        // Недоступність Нової Пошти не зберігається в сесії, тож список вище ще може містити ціну з адмінки.
        if ($item && $quote->unpriced()) {
            DeliveryPriceView::unpriced($item, (float)$cartData->total_price, $translations->getTranslation('sviat__novaposhta__cart_price_by_manager'));
        }
        if ($item && ($quote->price !== null || !empty($item->is_free_delivery) || $quote->unpriced())) {
            $answer['unpriced'] = $quote->unpriced() && empty($item->is_free_delivery);
            $answer['price'] = (float)$item->price;
            $answer['priceText'] = (string)$item->delivery_price_text;
            $answer['totalWithDelivery'] = (float)$item->total_price_with_delivery;
            $answer['free'] = !empty($item->is_free_delivery);
            if ($quote->codFee !== null && !$answer['free']) {
                $answer['codFee'] = $quote->codFee;
                $answer['codFeeText'] = $money->convert($quote->codFee) . ' ' . $this->currencySign($entityFactory);
            }
        }
        $this->json($response, $answer);
    }

    /** Підказки вулиць для адресної доставки (`ajax/novaposhta/streets?settlement_id=&q=`). */
    public function streets(Request $request, Response $response, StreetSearch $streets, CartQuotes $quotes, Cart $cart, UserHelper $userHelper, EntityFactory $entityFactory)
    {
        $settlementId = (int)$request->get('settlement_id', 'integer');
        $settlement = $settlementId > 0 ? $entityFactory->get(SettlementEntity::class)->get($settlementId) : null;
        $query = self::typed($request);
        if (!$settlement || !is_string($query) || empty(self::visitorCart($cart, $userHelper)->purchases)) {
            $response->setStatusCode(400);
            $this->json($response, ['error' => 'input']);

            return;
        }
        if (!$quotes->allowStreetLookup()) {
            $response->setStatusCode(429);
            $this->json($response, ['streets' => [], 'problem' => 'busy']);

            return;
        }
        $this->json($response, $streets->find($settlement, $query));
    }

    private function currencySign(EntityFactory $entityFactory): string
    {
        $currencies = $entityFactory->get(CurrenciesEntity::class);
        $currency = $currencies->get((int)($_SESSION['currency_id'] ?? 0)) ?: $currencies->getMainCurrency();

        return (string)($currency->sign ?? '');
    }

    private function json(Response $response, array $data): void
    {
        $response->setContent(json_encode($data, JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }

    /**
     * Текст пошуку як введено. Request::get() типово кодує HTML, що перетворює "Кам'янець" на
     * "Кам&#039;янець" і нічого не знаходить; значення лише підставляється в запити й повертається як JSON.
     */
    private static function typed(Request $request): mixed
    {
        return $request->get('q', null, null, false);
    }

    /**
     * Кошик відвідувача так, як його бачить сторінка кошика. Ці endpoints оминають AbstractController ядра,
     * чий mergeCart(true) і завантажує кошик авторизованого покупця: Cart::init() лишає його порожнім.
     */
    private static function visitorCart(Cart $cart, UserHelper $userHelper): Cart
    {
        $userHelper->mergeCart(true);

        return $cart->get();
    }
}
