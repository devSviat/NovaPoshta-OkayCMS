<?php

namespace Okay\Modules\Sviat\NovaPoshta\Controllers;

use Okay\Core\Cart;
use Okay\Core\Languages;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Modules\Sviat\NovaPoshta\Cart\CartMetrics;
use Okay\Modules\Sviat\NovaPoshta\Cart\DivisionList;
use Okay\Modules\Sviat\NovaPoshta\Cart\SettlementSearch;

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

    private function json(Response $response, array $data): void
    {
        $response->setContent(json_encode($data, JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }
}
