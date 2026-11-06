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
use Okay\Core\FrontTranslations;
use Okay\Core\Money;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\DeliveriesEntity;
use Okay\Modules\Sviat\NovaPoshta\Quote\CartQuotes;
use Okay\Modules\Sviat\NovaPoshta\Quote\DeliveryPriceView;

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

    /** Price and term for the chosen point (`ajax/novaposhta/quote?delivery_id=&division_id=&payment_id=`). */
    public function quote(Request $request, Response $response, CartQuotes $quotes, Cart $cart, EntityFactory $entityFactory, Money $money, FrontTranslations $translations)
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

        $answer = ['price' => null, 'priceText' => null, 'totalWithDelivery' => null, 'codFee' => null, 'codFeeText' => null, 'term' => $quote->term?->format('Y-m-d'), 'problem' => $quote->problem];
        if ($quote->price !== null) {
            $currency = $entityFactory->get(CurrenciesEntity::class)->get((int)($_SESSION['currency_id'] ?? 0)) ?: $entityFactory->get(CurrenciesEntity::class)->getMainCurrency();
            $convert = static fn(float $amount): string => (string)$money->convert($amount);
            DeliveryPriceView::apply($delivery, $quote->price, (float)$cartData->total_price, $convert, (string)($currency->sign ?? ''), $translations->getTranslation('cart_paid_separate'), $translations->getTranslation('cart_free'));
            $answer['price'] = (float)$delivery->price;
            $answer['priceText'] = (string)$delivery->delivery_price_text;
            $answer['totalWithDelivery'] = (float)$delivery->total_price_with_delivery;
            if ($quote->codFee !== null && !$delivery->is_free_delivery) {
                $answer['codFee'] = $quote->codFee;
                $answer['codFeeText'] = $convert($quote->codFee) . ' ' . (string)($currency->sign ?? '');
            }
        }
        $this->json($response, $answer);
    }

    private function json(Response $response, array $data): void
    {
        $response->setContent(json_encode($data, JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }
}
