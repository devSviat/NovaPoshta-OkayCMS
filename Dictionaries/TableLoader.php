<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store\DictionaryStore;

/** Writes only rows whose hash changed, in batches, and counts what happened. */
final class TableLoader
{
    private const int BATCH = 500;

    /** @var array<int, string> */
    private array $hashes;
    /** @var list<array> */
    private array $pendingRows = [];
    /** @var list<int> */
    private array $pendingTouch = [];
    private int $added = 0;
    private int $changed = 0;
    private int $unchanged = 0;

    public function __construct(
        private readonly DictionaryStore $store,
        private readonly string $table,
        private readonly int $run,
        private readonly bool $touchUnchanged,
    ) {
        $this->hashes = $store->hashes($table);
    }

    public function put(array $row): void
    {
        $known = $this->hashes[$row['id']] ?? null;
        if ($known === $row['row_hash']) {
            $this->unchanged++;
            if ($this->touchUnchanged) {
                $this->pendingTouch[] = $row['id'];
                if (count($this->pendingTouch) >= self::BATCH) {
                    $this->flushTouch();
                }
            }

            return;
        }

        $known === null ? $this->added++ : $this->changed++;
        $this->hashes[$row['id']] = $row['row_hash'];
        $this->pendingRows[] = $row;
        if (count($this->pendingRows) >= self::BATCH) {
            $this->flushRows();
        }
    }

    public function isKnown(int $id): bool
    {
        return isset($this->hashes[$id]);
    }

    public function finish(int $deleted = 0): UpdateCounts
    {
        $this->flushRows();
        $this->flushTouch();

        return new UpdateCounts($this->added, $this->changed, $deleted, $this->unchanged);
    }

    private function flushRows(): void
    {
        if ($this->pendingRows !== []) {
            $this->store->upsert($this->table, $this->pendingRows, $this->run);
            $this->pendingRows = [];
        }
    }

    private function flushTouch(): void
    {
        if ($this->pendingTouch !== []) {
            $this->store->touch($this->table, $this->pendingTouch, $this->run);
            $this->pendingTouch = [];
        }
    }
}
