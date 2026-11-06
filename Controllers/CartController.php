<?php

namespace Okay\Modules\Sviat\NovaPoshta\Controllers;

use Okay\Core\Cart;
use Okay\Core\Languages;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Cart\DivisionList;
use Okay\Modules\Sviat\NovaPoshta\Cart\SettlementSearch;
use Okay\Core\EntityFactory;
use Okay\Helpers\DeliveriesHelper;
use Okay\Entities\PaymentsEntity;
use Okay\Core\Money;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\DeliveriesEntity;
use Okay\Modules\Sviat\NovaPoshta\Quote\CartQuotes;

/**
 * Public cart endpoints. They read only the Nova Poshta dictionary and the visitor's own
 * cart: no orders, no personal data (design §11).
 */
class CartController
{
    public function settlements(Request $request, Response $response, SettlementSearch $search, Languages $languages)
    {
        $query = $request->get('q');
        $this->json($response, ['settlements' => is_string($query) ? $search->find($query, (string)$languages->getLangLabel()) : []]);
    }

    public function divisions(Request $request, Response $response, DivisionList $divisions, Cart $cart, Languages $languages)
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
            CartMetrics::fromCart($cart->get()),
            (string)$languages->getLangLabel(),
            $groupId ? (int)$groupId : null,
            (string)$request->get('q'),
        ));
    }

    /**
     * Price and term for the chosen point (`ajax/novaposhta/quote?delivery_id=&division_id=&payment_id=`).
     * The method's numbers come from the core's own cart list, so every module extending it
     * (a promo's free shipping) shapes the answer exactly as it shapes the page.
     */
    public function quote(Request $request, Response $response, CartQuotes $quotes, Cart $cart, EntityFactory $entityFactory, Money $money, DeliveriesHelper $deliveriesHelper)
    {
        $deliveryId = (int)$request->get('delivery_id', 'integer');
        $delivery = $deliveryId > 0 ? $entityFactory->get(DeliveriesEntity::class)->get($deliveryId) : null;
        $cartData = $cart->get();
        $quote = $delivery && !empty($delivery->enabled)
            ? $quotes->quote($delivery, (int)$request->get('division_id', 'integer'), (int)$request->get('payment_id', 'integer'), $cartData)
            : null;
        if ($quote === null) {
            $response->setStatusCode(400);
            $this->json($response, ['error' => 'input']);

            return;
        }
        if ($quote->problem === 'busy') {
            $response->setStatusCode(429);
        }

        $answer = ['price' => null, 'priceText' => null, 'totalWithDelivery' => null, 'free' => false, 'codFee' => null, 'codFeeText' => null, 'term' => $quote->term?->format('Y-m-d'), 'problem' => $quote->problem];
        $payments = $entityFactory->get(PaymentsEntity::class)->mappedBy('id')->find(['enabled' => 1]);
        $item = $deliveriesHelper->getCartDeliveriesList($cartData, $payments)[$deliveryId] ?? null;
        if ($item && ($quote->price !== null || !empty($item->is_free_delivery))) {
            $answer['price'] = (float)$item->price;
            $answer['priceText'] = (string)$item->delivery_price_text;
            $answer['totalWithDelivery'] = (float)$item->total_price_with_delivery;
            $answer['free'] = !empty($item->is_free_delivery);
            if ($quote->codFee !== null && !$answer['free']) {
                $currency = $entityFactory->get(CurrenciesEntity::class)->get((int)($_SESSION['currency_id'] ?? 0)) ?: $entityFactory->get(CurrenciesEntity::class)->getMainCurrency();
                $answer['codFee'] = $quote->codFee;
                $answer['codFeeText'] = $money->convert($quote->codFee) . ' ' . (string)($currency->sign ?? '');
            }
        }
        $this->json($response, $answer);
    }

    private function json(Response $response, array $data): void
    {
        $response->setContent(json_encode($data, JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }
}
