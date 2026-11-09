<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Core\EntityFactory;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryStore;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;

/** What the waybill block of an order page shows: the waybill card, or the form for a new one. */
final class WaybillView
{

    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly OrderDeliveryStore $snapshots,
        private readonly WaybillRows $rows,
        private readonly WaybillDrafts $drafts,
        private readonly CodPayments $codPayments,
    ) {
    }

    /** @return ?array null when the order is not delivered by this module: the block is not shown */
    public function forOrder(int $orderId): ?array
    {
        $order = $orderId > 0 ? $this->entityFactory->get(OrdersEntity::class)->get($orderId) : null;
        if (!$order || !$this->snapshots->forOrder($orderId)) {
            return null;
        }
        $view = ['orderId' => $orderId, 'row' => null, 'draft' => null, 'refusal' => null];

        $row = $this->rows->forOrder($orderId);
        if (WaybillService::needsCheck($row, time())) {
            // NP may hold a waybill of this order: the manager asks it before anything new is made.
            $view['check'] = true;

            return $view;
        }
        if (WaybillService::isPending($row, time())) {
            $view['refusal'] = 'waybill_pending';
            $view['refusalKey'] = 'sviat__novaposhta__waybill_pending';

            return $view;
        }
        if (PendingWaybill::is($row)) {
            $row = null;
        }
        if ($row !== null) {
            $view['row'] = [
                'number' => (string)$row->number,
                'numberSpaced' => trim(chunk_split((string)$row->number, 4, ' ')),
                'attached' => $row->api === 'attached',
                'deletable' => $row->api !== 'attached' && (!is_numeric($row->status_code ?? null) || in_array((int)$row->status_code, WaybillStatus::DELETABLE, true)),
                'trackingUrl' => WaybillNumber::trackingUrl((string)$row->number),
                'card' => WaybillCard::fromDocument(is_string($row->tracking ?? null) ? (json_decode($row->tracking, true) ?: null) : null, $row),
                'services' => json_decode((string)$row->services, true) ?: [],
                'audit' => $row->audit_status ? AuditResult::fromArray(json_decode((string)$row->audit, true) ?: ['status' => $row->audit_status])->toArray() : null,
            ];

            return $view;
        }

        $draft = $this->drafts->forOrder($orderId);
        if (is_string($draft)) {
            $view['refusal'] = $draft;
            $view['refusalKey'] = 'sviat__novaposhta__' . $draft;

            return $view;
        }
        $view['draft'] = $draft;
        $view['paid'] = !empty($order->paid);
        $view['codPayment'] = $this->codPayments->isCod((int)$order->payment_method_id);
        $view['textAddress'] = $draft->mode === DeliveryMode::Address && $draft->streetRef === null;
        $view['duplicatePoint'] = $draft->mode === DeliveryMode::Points && $this->numberRepeats($draft);

        return $view;
    }

    /** Old-API waybills go by point number, and a few numbers repeat inside one settlement (design §2). */
    private function numberRepeats(WaybillDraft $draft): bool
    {
        $division = $this->entityFactory->get(DivisionEntity::class)->get((int)$draft->divisionId);
        if (!$division) {
            return false;
        }

        return $this->entityFactory->get(DivisionEntity::class)->count(['settlement_id' => (int)$division->settlement_id, 'number' => (string)$division->number]) > 1;
    }
}
