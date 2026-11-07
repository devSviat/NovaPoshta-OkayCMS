<?php

namespace Okay\Modules\Sviat\NovaPoshta\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\BackendTranslations;
use Okay\Core\Languages;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshta\Cart\DivisionList;
use Okay\Modules\Sviat\NovaPoshta\Cart\SettlementSearch;
use Okay\Modules\Sviat\NovaPoshta\Cart\StreetSearch;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Extenders\BackendExtender;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderParcel;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderPointChange;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderPointResult;

/**
 * The point/address editor of an order page posts here (`controller=Sviat.NovaPoshta.OrderPointAdmin`).
 * Access needs the `orders` permission; every action also needs the CSRF token.
 */
class OrderPointAdmin extends IndexAdmin
{
    private const array INPUT = ['settlement_id' => 12, 'division_id' => 12, 'street' => 100, 'street_ref' => 36, 'house' => 10, 'flat' => 10, 'entrance' => 5, 'floor' => 3, 'note' => 40, 'elevator' => 1, 'lifting' => 1];

    public function fetch(
        OrderPointChange $change,
        OrderParcel $parcel,
        SettlementSearch $settlements,
        DivisionList $divisions,
        StreetSearch $streets,
        BackendExtender $blocks,
        Languages $languages,
        BackendTranslations $translations,
    ) {
        if (!$this->request->method('post')) {
            $this->answer(405, ['ok' => false]);

            return;
        }
        // Stock 4.5.3 checks session_id only after the controller has run.
        if (!$this->request->checkSession()) {
            $this->answer(403, ['ok' => false]);

            return;
        }
        $lang = (string)$languages->getLangLabel();
        $action = (string)$this->request->post('action');

        if ($action === 'settlements') {
            $query = $this->request->post('q');
            $found = is_string($query) ? $settlements->find(mb_substr($query, 0, 60), $lang, 10, $this->request->post('mode') !== 'address') : [];
            $this->answer(200, ['settlements' => $found]);

            return;
        }
        if ($action === 'streets') {
            $settlement = $this->entityFactory->get(SettlementEntity::class)->get((int)$this->request->post('settlement_id', 'integer'));
            $query = $this->request->post('q');
            $this->answer(200, $settlement && is_string($query) ? $streets->find($settlement, $query) : ['streets' => [], 'problem' => null]);

            return;
        }

        $orderId = (int)$this->request->post('order_id', 'integer');
        $order = $orderId > 0 ? $this->entityFactory->get(OrdersEntity::class)->get($orderId) : null;
        if (!$order) {
            $this->answer(404, ['ok' => false]);

            return;
        }

        if ($action === 'points') {
            $groupId = (int)$this->request->post('group_id', 'integer');
            $query = $this->request->post('q');
            $this->answer(200, $divisions->forSettlement(
                (int)$this->request->post('settlement_id', 'integer'),
                $parcel->metrics($order),
                $lang,
                $groupId > 0 ? $groupId : null,
                is_string($query) ? mb_substr($query, 0, 60) : '',
            ));

            return;
        }

        $result = match ($action) {
            'save' => $change->save($orderId, $this->input()),
            'apply_price' => $change->applyPrice($orderId),
            default => null,
        };
        if ($result === null) {
            $this->answer(400, ['ok' => false]);

            return;
        }

        $body = self::view($result, $translations);
        if ($result->ok) {
            // Both blocks are drawn again: the waybill form takes the new point at once.
            $blocks->assignOrderDelivery($this->entityFactory->get(OrdersEntity::class)->get($orderId));
            $body['deliveryHtml'] = $this->design->fetch('order_delivery.tpl');
            $body['waybillHtml'] = $this->design->fetch('waybill.tpl');
        }
        $this->answer(200, $body);
    }

    /** @return array<string, string> known fields only, cut to their limits */
    private function input(): array
    {
        $input = [];
        foreach (self::INPUT as $name => $limit) {
            $value = $this->request->post($name);
            if (is_string($value)) {
                $input[$name] = mb_substr(trim($value), 0, $limit);
            }
        }

        return $input;
    }

    private static function view(OrderPointResult $result, BackendTranslations $translations): array
    {
        $text = static fn(?string $key): ?string => $key === null ? null : (string)$translations->getTranslation('sviat__novaposhta__' . $key);

        return [
            'ok' => $result->ok,
            'error' => $text($result->error),
            'warning' => $text($result->warning),
            'price' => $result->price,
        ];
    }

    private function answer(int $status, array $body): void
    {
        $this->response->setStatusCode($status);
        $this->response->setContent(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), RESPONSE_JSON);
    }
}
