<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** Where the old API's city Ref of a settlement is kept between requests. */
interface CityRefStore
{
    /** @return array{ref: ?string, checkedAt: int}|null */
    public function cached(int $settlementId): ?array;

    public function remember(int $settlementId, ?string $ref, int $now): void;

    /** @return list<string> numbers of working points in the settlement that are not repeated there */
    public function bridgeNumbers(int $settlementId, int $limit): array;
}
