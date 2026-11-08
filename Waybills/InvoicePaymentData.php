<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Core\EntityFactory;
use Okay\Entities\PaymentsEntity;

/** The organization an order was invoiced to by Sviat/InvoicePayment, which may be absent (it is not on the fork). */
final class InvoicePaymentData
{
    public const string ENTITY = 'Okay\\Modules\\Sviat\\InvoicePayment\\Entities\\InvoicePaymentDataEntity';
    private const string PAYMENT_MODULE = 'Sviat/InvoicePayment';

    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly string $entityClass = self::ENTITY,
    ) {
    }

    /** @return ?array{edrpou: string, company: string} */
    public function forOrder(object $order): ?array
    {
        if (!class_exists($this->entityClass) || empty($order->id) || empty($order->payment_method_id)) {
            return null;
        }
        $payment = $this->entityFactory->get(PaymentsEntity::class)->get((int)$order->payment_method_id);
        if (!is_object($payment) || ($payment->module ?? null) !== self::PAYMENT_MODULE) {
            return null;
        }
        try {
            $row = $this->entityFactory->get($this->entityClass)->findOne(['order_id' => (int)$order->id]);
        } catch (\Throwable) {
            // The module's table may be gone while its files stay.
            return null;
        }

        return self::read($payment, is_object($row) ? $row : null);
    }

    /** @return ?array{edrpou: string, company: string} */
    public static function read(?object $payment, ?object $row): ?array
    {
        if ($payment === null || $row === null || ($payment->module ?? null) !== self::PAYMENT_MODULE) {
            return null;
        }
        $edrpou = trim((string)($row->edrpou ?? ''));
        $company = trim((string)($row->company_name ?? ''));

        return $edrpou !== '' && $company !== '' ? ['edrpou' => $edrpou, 'company' => $company] : null;
    }
}
