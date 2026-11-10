<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Core\Database;
use Okay\Core\EntityFactory;
use Okay\Core\QueryFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Groups\DivisionCategory;

/** Цифри для адмінки: з чого покупці реально можуть обирати (лише робочі пункти видачі). */
final class DictionaryCounts
{
    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly Database $db,
        private readonly QueryFactory $queryFactory,
    ) {
    }

    /** @return array<string, int> робочі пункти видачі за категоріями; один прохід по таблиці, а не по одному на категорію */
    public function byCategory(): array
    {
        $select = $this->queryFactory->newSelect()
            ->cols(['category', 'COUNT(*) AS n'])
            ->from(DivisionEntity::getTable())
            ->where("status = 'Working'")
            ->groupBy(['category']);

        return self::fromRows($this->db->query($select) === false ? [] : $this->db->results());
    }

    /**
     * @param iterable<object{category: string, n: int|string}> $rows
     * @return array<string, int> кожна категорія, нуль, де немає рядка
     */
    public static function fromRows(iterable $rows): array
    {
        $counts = array_fill_keys(array_map(static fn(DivisionCategory $c): string => $c->value, DivisionCategory::cases()), 0);
        foreach ($rows as $row) {
            if (isset($counts[$row->category])) {
                $counts[$row->category] = (int)$row->n;
            }
        }

        return $counts;
    }

    /** @return array{divisions:int, postomats:int, settlements:int, boosted:int} */
    public function summary(array $byCategory): array
    {
        $settlements = $this->entityFactory->get(SettlementEntity::class);

        return [
            'divisions' => array_sum($byCategory),
            'postomats' => $byCategory[DivisionCategory::Postomat->value] ?? 0,
            'settlements' => (int)$settlements->count(),
            'boosted' => (int)$settlements->count(['boosted' => true]),
        ];
    }
}
