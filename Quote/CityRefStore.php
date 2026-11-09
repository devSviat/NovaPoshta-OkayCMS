<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** Де між запитами зберігається Ref міста старого API для населеного пункту. */
interface CityRefStore
{
    /** @return array{ref: ?string, checkedAt: int}|null */
    public function cached(int $settlementId): ?array;

    public function remember(int $settlementId, ?string $ref, int $now): void;

    /** @return list<string> номери робочих пунктів видачі в населеному пункті, які там не повторюються */
    public function bridgeNumbers(int $settlementId, int $limit): array;
}
