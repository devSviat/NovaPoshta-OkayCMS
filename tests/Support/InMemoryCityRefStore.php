<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Modules\Sviat\NovaPoshta\Quote\CityRefStore;

final class InMemoryCityRefStore implements CityRefStore
{
    /** @var array<int, array{ref: ?string, checkedAt: int}> */
    public array $rows = [];
    /** @var array<int, list<string>> */
    public array $numbers = [];

    public function cached(int $settlementId): ?array
    {
        return $this->rows[$settlementId] ?? null;
    }

    public function remember(int $settlementId, ?string $ref, int $now): void
    {
        $this->rows[$settlementId] = ['ref' => $ref, 'checkedAt' => $now];
    }

    public function bridgeNumbers(int $settlementId, int $limit): array
    {
        return array_slice($this->numbers[$settlementId] ?? [], 0, $limit);
    }
}
