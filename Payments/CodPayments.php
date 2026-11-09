<?php

namespace Okay\Modules\Sviat\NovaPoshta\Payments;

use Okay\Core\EntityFactory;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;

/**
 * Способи оплати, позначені "післяплата Новою Поштою", кожен зі своїм видом. Вибір такого способу в
 * кошику — це вибір покупцем післяплати; окремого чекбокса в кошику немає.
 */
final class CodPayments
{
    /** Власне поле в таблиці оплат ядра; значення — прапорці CodKind. */
    public const string FIELD = 'sviat__novaposhta__cod';

    public function __construct(private readonly EntityFactory $entityFactory)
    {
    }

    /** "Manual" у селекті типу оплати: ядро зберігає його як рядок 'null'. */
    public static function isManual(mixed $module): bool
    {
        return $module === null || $module === '' || $module === 'null';
    }

    /** Онлайн-оплати (будь-який модуль оплати) ніколи не є післяплатою, хоч би що було в колонці. */
    public static function kindOf(object $payment): ?CodKind
    {
        if (!self::isManual($payment->module ?? null)) {
            return null;
        }

        return CodKind::fromFlag((int)($payment->{self::FIELD} ?? 0));
    }

    /** @return list<object> кожен спосіб оплати з `cod_kind` ('money'|'control'|null) і `manual` */
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

    /** @return list<int> увімкнені способи оплати, чия післяплата — грошовий переказ (кошик показує її комісію) */
    public function moneyIds(): array
    {
        return $this->idsOf(static fn(object $m): bool => !empty($m->enabled) && $m->cod_kind === CodKind::Money->value);
    }

    /**
     * Чи можна оплатити спосіб доставки післяплатою: тоді кошик показує комісію
     * під цією оплатою, перш ніж покупець її обере.
     * @param list<int|string> $deliveryPaymentIds DeliveriesEntity::getDeliveryPayments
     * @param list<int> $codIds
     */
    public static function wantsFee(array $deliveryPaymentIds, array $codIds): bool
    {
        return array_intersect(array_map(intval(...), $deliveryPaymentIds), $codIds) !== [];
    }

    /**
     * @param callable(object): bool $wanted
     * @return list<int>
     */
    private function idsOf(callable $wanted): array
    {
        return array_values(array_map(static fn(object $m): int => (int)$m->id, array_filter($this->all(), $wanted)));
    }
}
