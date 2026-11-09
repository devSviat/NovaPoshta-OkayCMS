<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Core\Database;
use Okay\Core\EntityFactory;
use Okay\Core\QueryFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Groups\DivisionCategory;

/** Figures for the admin: what customers can actually choose from (working points only). */
final class DictionaryCounts
{
    /** @var array<string, int>|null the settings page and the legacy check both ask within one request */
    private ?array $byCategory = null;

    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly Database $db,
        private readonly QueryFactory $queryFactory,
    ) {
    }

    /** @return array<string, int> working points per category; one pass over the table, not one per category */
    public function byCategory(): array
    {
        if ($this->byCategory !== null) {
            return $this->byCategory;
        }
        $select = $this->queryFactory->newSelect()
            ->cols(['category', 'COUNT(*) AS n'])
            ->from(DivisionEntity::getTable())
            ->where("status = 'Working'")
            ->groupBy(['category']);

        return $this->byCategory = self::fromRows($this->db->query($select) === false ? [] : $this->db->results());
    }

    /**
     * @param iterable<object{category: string, n: int|string}> $rows
     * @return array<string, int> every category, zero where no row
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
