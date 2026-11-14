<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Core\Database;
use Okay\Core\QueryFactory;
use Okay\Entities\OrdersEntity;

/**
 * Через Database, а не OrdersEntity: Entity повертає [] і тоді, коли запит упав (Database ковтає помилку),
 * а тут порожня відповідь означала б «замовлень немає» й видалення всіх рядків модуля.
 */
final class DbExistingOrders implements ExistingOrders
{
    private const int CHUNK = 500;

    public function __construct(
        private readonly Database $db,
        private readonly QueryFactory $queryFactory,
    ) {
    }

    public function among(array $orderIds): ?array
    {
        $existing = [];
        foreach (array_chunk($orderIds, self::CHUNK) as $chunk) {
            $select = $this->queryFactory->newSelect()
                ->cols(['o.id'])
                ->from(OrdersEntity::getTable() . ' AS o')
                ->where('o.id IN (:ids)')
                ->bindValue('ids', $chunk);
            if ($this->db->query($select) === false) {
                return null;
            }
            foreach ($this->db->results('id') as $id) {
                $existing[(int)$id] = true;
            }
        }

        return $existing;
    }

    public function maxId(): ?int
    {
        $select = $this->queryFactory->newSelect()
            ->cols(['MAX(o.id) AS max_id'])
            ->from(OrdersEntity::getTable() . ' AS o');
        if ($this->db->query($select) === false) {
            return null;
        }

        return (int)$this->db->result('max_id');
    }
}
