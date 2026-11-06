<?php

namespace Okay\Modules\Sviat\NovaPoshta\Payments;

use Okay\Core\EntityFactory;
use Okay\Entities\PaymentsEntity;

/**
 * Payment methods marked "cash on delivery by Nova Poshta". Choosing one in the cart is the
 * customer's choice of cash on delivery (author's decision 06.11.2026: no checkbox in the cart).
 */
final class CodPayments
{
    /** Own field on the core payments table; NovaposhtaCost's flag lives under its own name (§9). */
    public const string FIELD = 'sviat__novaposhta__cod';

    public function __construct(private readonly EntityFactory $entityFactory)
    {
    }

    /** @return list<object> every payment method with `cod` set to 0/1 */
    public function all(): array
    {
        $methods = array_values($this->entityFactory->get(PaymentsEntity::class)->noLimit()->find());
        foreach ($methods as $method) {
            $method->cod = (int)($method->{self::FIELD} ?? 0);
        }

        return $methods;
    }

    public function isCod(int $paymentId): bool
    {
        if ($paymentId <= 0) {
            return false;
        }
        $method = $this->entityFactory->get(PaymentsEntity::class)->get($paymentId);

        return $method && (int)($method->{self::FIELD} ?? 0) === 1;
    }

    /** @param mixed $posted ids from the settings form */
    public function save(mixed $posted): void
    {
        $entity = $this->entityFactory->get(PaymentsEntity::class);
        $existing = array_map(static fn(object $m): int => (int)$m->id, $this->all());
        $marked = self::normalize($posted, $existing);
        foreach ($existing as $id) {
            $entity->update($id, [self::FIELD => in_array($id, $marked, true) ? 1 : 0]);
        }
    }

    /**
     * @param list<int> $existing
     * @return list<int> posted ids that exist, ascending, without repeats
     */
    public static function normalize(mixed $posted, array $existing): array
    {
        if (!is_array($posted)) {
            return [];
        }
        $ids = [];
        foreach ($posted as $value) {
            if ((is_string($value) || is_int($value)) && preg_match('/^\d+$/', (string)$value) && in_array((int)$value, $existing, true)) {
                $ids[(int)$value] = (int)$value;
            }
        }
        ksort($ids);

        return array_values($ids);
    }
}
