<?php

namespace Okay\Modules\Sviat\NovaPoshta\Payments;

use Okay\Core\EntityFactory;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;

/**
 * Payment methods marked "cash on delivery by Nova Poshta", each with its kind. Choosing one in the
 * cart is the customer's choice of cash on delivery (author's decision 06.11.2026: no checkbox in the cart).
 */
final class CodPayments
{
    /** Own field on the core payments table; values are CodKind flags (§9). */
    public const string FIELD = 'sviat__novaposhta__cod';

    public function __construct(private readonly EntityFactory $entityFactory)
    {
    }

    /** "Manual" in the payment's type select: the core keeps it as the string 'null'. */
    public static function isManual(mixed $module): bool
    {
        return $module === null || $module === '' || $module === 'null';
    }

    /** Online payments (any payment module) are never cash on delivery, whatever the column holds. */
    public static function kindOf(object $payment): ?CodKind
    {
        if (!self::isManual($payment->module ?? null)) {
            return null;
        }

        return CodKind::fromFlag((int)($payment->{self::FIELD} ?? 0));
    }

    /** @return list<object> every payment method with `cod_kind` ('money'|'control'|null) and `manual` */
    public function all(): array
    {
        $methods = array_values($this->entityFactory->get(PaymentsEntity::class)->noLimit()->find());
        foreach ($methods as $method) {
            $method->cod_kind = self::kindOf($method)?->value;
            $method->manual = self::isManual($method->module ?? null);
        }

        return $methods;
    }

    public function kind(int $paymentId): ?CodKind
    {
        if ($paymentId <= 0) {
            return null;
        }
        $method = $this->entityFactory->get(PaymentsEntity::class)->get($paymentId);

        return $method ? self::kindOf($method) : null;
    }

    public function kindOfPayment(object $payment): ?CodKind
    {
        return self::kindOf($payment);
    }

    public function isCod(int $paymentId): bool
    {
        return $this->kind($paymentId) !== null;
    }

    /** @return list<int> payment methods whose cash on delivery is a money transfer (the cart shows its commission) */
    public function moneyIds(): array
    {
        return $this->idsOf(static fn(?string $kind): bool => $kind === CodKind::Money->value);
    }

    /**
     * Whether a delivery method can be paid by cash on delivery: then the cart shows the commission
     * under that payment before the customer picks it.
     * @param list<int|string> $deliveryPaymentIds DeliveriesEntity::getDeliveryPayments
     * @param list<int> $codIds
     */
    public static function wantsFee(array $deliveryPaymentIds, array $codIds): bool
    {
        return array_intersect(array_map('intval', $deliveryPaymentIds), $codIds) !== [];
    }

    /**
     * @param callable(?string): bool $wanted
     * @return list<int>
     */
    private function idsOf(callable $wanted): array
    {
        return array_values(array_map(static fn(object $m): int => (int)$m->id, array_filter($this->all(), static fn(object $m): bool => $wanted($m->cod_kind))));
    }
}
