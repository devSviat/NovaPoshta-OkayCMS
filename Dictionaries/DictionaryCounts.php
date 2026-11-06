<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Groups\DivisionCategory;

/** Figures for the admin: what customers can actually choose from (working points only). */
final class DictionaryCounts
{
    public function __construct(private readonly EntityFactory $entityFactory)
    {
    }

    /** @return array<string, int> working points per category */
    public function byCategory(): array
    {
        $divisions = $this->entityFactory->get(DivisionEntity::class);
        $counts = [];
        foreach (DivisionCategory::cases() as $category) {
            $counts[$category->value] = (int)$divisions->count(['category' => $category->value, 'status' => 'Working']);
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
