<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store;

/**
 * Bulk writes of dictionary rows. $table is 'divisions' or 'settlements'.
 * Every method throws on a failed query: a silently failed write followed by
 * deleteNotSeen() would wipe rows that are still valid.
 */
interface DictionaryStore
{
    public const string DIVISIONS = 'divisions';
    public const string SETTLEMENTS = 'settlements';

    /** @return array<int, string> id → row_hash */
    public function hashes(string $table): array;

    /** @param list<array> $rows inserted or replaced; `boost` of settlements is kept */
    public function upsert(string $table, array $rows, int $run): void;

    /** Marks unchanged rows as present in run $run. @param list<int> $ids */
    public function touch(string $table, array $ids, int $run): void;

    /** Deletes rows the full run $run did not see; call only after the run read everything. */
    public function deleteNotSeen(string $table, int $run): int;

    /** @param list<int> $ids */
    public function delete(string $table, array $ids): int;

    /** Marks the settlements that have a working pickup point (the cart searches only those). */
    public function refreshPickup(): void;

    /** @param array<int, int> $boostById settlements not listed get 0 */
    public function setBoost(array $boostById): void;
}
