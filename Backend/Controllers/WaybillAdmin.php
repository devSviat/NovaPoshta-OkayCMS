<?php

namespace Okay\Modules\Sviat\NovaPoshta\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\BackendTranslations;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshta\Waybills\NpPhone;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillDraft;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillDrafts;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillInput;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillOutcome;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillService;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillView;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillTracking;

/**
 * The waybill block of an order page posts here (`controller=Sviat.NovaPoshta.WaybillAdmin`).
 * Access to the controller needs the `orders` permission; every action also needs the CSRF token.
 */
class WaybillAdmin extends IndexAdmin
{
    public function fetch(WaybillService $service, WaybillDrafts $drafts, WaybillView $view, WaybillTracking $tracking, BackendTranslations $translations)
    {
        if (!$this->request->method('post')) {
            $this->answer(405, ['ok' => false]);

            return;
        }
        // Stock 4.5.3 checks session_id only after the controller has run.
        if (!$this->request->checkSession()) {
            $this->answer(403, ['ok' => false]);

            return;
        }
        $orderId = (int)$this->request->post('order_id', 'integer');
        $order = $orderId > 0 ? $this->entityFactory->get(OrdersEntity::class)->get($orderId) : null;
        if (!$order) {
            $this->answer(404, ['ok' => false]);

            return;
        }

        $outcome = match ((string)$this->request->post('action')) {
            'create' => $this->create($service, $drafts, $orderId),
            'attach' => $service->attach($orderId, (string)$this->request->post('number'), NpPhone::normalize((string)($order->phone ?? ''))),
            'remove' => $service->remove($orderId),
            'unlink' => $service->unlink($orderId),
            'lookup' => $service->lookup($orderId, NpPhone::normalize((string)($order->phone ?? ''))),
            'refresh' => $tracking->refresh($orderId, NpPhone::normalize((string)($order->phone ?? ''))),
            default => null,
        };
        if ($outcome === null) {
            $this->answer(400, ['ok' => false]);

            return;
        }

        $body = self::view($outcome, $translations);
        if ($outcome->ok) {
            // The block is drawn again from the saved state: the card after create/attach, the form after remove.
            $this->design->assign('np_waybill', $view->forOrder($orderId));
            $body['html'] = $this->design->fetch('waybill.tpl');
        }
        $this->answer(200, $body);
    }

    private function create(WaybillService $service, WaybillDrafts $drafts, int $orderId): WaybillOutcome
    {
        $draft = $drafts->forOrder($orderId);
        if (is_string($draft)) {
            return WaybillOutcome::refused($draft);
        }
        $edited = $draft->withInput(WaybillInput::fromPost($_POST));
        if (!$edited instanceof WaybillDraft) {
            return new WaybillOutcome(false, null, array_map(
                static fn(array $error): array => ['field' => $error['field'], 'message' => null, 'advice' => $error['key'], 'details' => ''],
                $edited,
            ));
        }

        return $service->create($orderId, $edited);
    }

    private static function view(WaybillOutcome $outcome, BackendTranslations $translations): array
    {
        $text = static fn(string $key): string => (string)$translations->getTranslation('sviat__novaposhta__' . $key);

        return [
            'ok' => $outcome->ok,
            'number' => $outcome->number,
            'issues' => array_map(static fn(array $issue): array => [
                'field' => $issue['field'],
                'message' => $issue['message'],
                'advice' => $text($issue['advice']),
                'details' => $issue['details'],
            ], $outcome->issues),
            'notices' => array_map($text, $outcome->notices),
            'audit' => $outcome->audit?->toArray(),
        ];
    }

    private function answer(int $status, array $body): void
    {
        $this->response->setStatusCode($status);
        $this->response->setContent(json_encode($body, JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }
}
