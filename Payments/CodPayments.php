<?php

namespace Okay\Modules\Sviat\NovaPoshta\Payments;

use Okay\Core\EntityFactory;
use Okay\Core\Settings;
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
    /** The one shop-wide kind of 0.9.0 and earlier; read only to interpret the old flag 1. */
    public const string LEGACY_KIND = 'sviat__novaposhta__cod_kind';

    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly Settings $settings,
    ) {
    }

    /**
     * For update_*: a disabled module's Init/services.php is not loaded, yet "Update" still runs.
     * @param callable(string): object $core resolves core services only
     */
    public static function forUpdate(callable $core): self
    {
        return new self($core(EntityFactory::class), $core(Settings::class));
    }

    /** "Manual" in the payment's type select: the core keeps it as the string 'null'. */
    public static function isManual(mixed $module): bool
    {
        return $module === null || $module === '' || $module === 'null';
    }

    /** Online payments (any payment module) are never cash on delivery, whatever the column holds. */
    public static function kindOf(object $payment, ?CodKind $legacy): ?CodKind
    {
        if (!self::isManual($payment->module ?? null)) {
            return null;
        }

        return CodKind::fromFlag((int)($payment->{self::FIELD} ?? 0), $legacy);
    }

    /** @return list<object> every payment method with `cod_kind` ('money'|'control'|null) and `manual` */
    public function all(): array
    {
        $legacy = $this->legacy();
        $methods = array_values($this->entityFactory->get(PaymentsEntity::class)->noLimit()->find());
        foreach ($methods as $method) {
            $method->cod_kind = self::kindOf($method, $legacy)?->value;
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

        return $method ? self::kindOf($method, $this->legacy()) : null;
    }

    public function kindOfPayment(object $payment): ?CodKind
    {
        return self::kindOf($payment, $this->legacy());
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
     * The old flag 1 rewritten as the kind it meant under the old shop-wide setting (update_0_10_0).
     * @param array<int, int> $flags payment id => stored flag
     * @return array<int, int> only the payments whose flag changes
     */
    public static function migratedFlags(array $flags, ?CodKind $legacy): array
    {
        $changed = [];
        foreach ($flags as $id => $flag) {
            if ($flag === 1) {
                $changed[$id] = (CodKind::fromFlag(1, $legacy) ?? CodKind::Money)->flag();
            }
        }

        return $changed;
    }

    /** @return int how many payment methods were rewritten */
    public function migrateLegacy(): int
    {
        $entity = $this->entityFactory->get(PaymentsEntity::class);
        $flags = [];
        foreach ($entity->noLimit()->find() as $method) {
            $flags[(int)$method->id] = (int)($method->{self::FIELD} ?? 0);
        }
        $changed = self::migratedFlags($flags, $this->legacy());
        foreach ($changed as $id => $flag) {
            $entity->update($id, [self::FIELD => $flag]);
        }

        return count($changed);
    }

    private function legacy(): ?CodKind
    {
        return CodKind::tryFrom((string)$this->settings->get(self::LEGACY_KIND));
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
