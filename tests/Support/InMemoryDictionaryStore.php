<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store\DictionaryStore;

final class InMemoryDictionaryStore implements DictionaryStore
{
    /** @var array<string, array<int, array>> */
    public array $tables = ['divisions' => [], 'settlements' => []];

    /** @var list<string> */
    public array $calls = [];

    public ?\RuntimeException $failOnUpsert = null;

    public function hashes(string $table): array
    {
        return array_map(static fn(array $row): string => $row['row_hash'], $this->tables[$table]);
    }

    public function upsert(string $table, array $rows, int $run): void
    {
        $this->calls[] = "upsert {$table} " . count($rows);
        if ($this->failOnUpsert) {
            throw $this->failOnUpsert;
        }
        foreach ($rows as $row) {
            $this->tables[$table][$row['id']] = ['boost' => $this->tables[$table][$row['id']]['boost'] ?? 0] + $row + ['seen_run' => $run];
            $this->tables[$table][$row['id']]['seen_run'] = $run;
        }
    }

    public function touch(string $table, array $ids, int $run): void
    {
        $this->calls[] = "touch {$table} " . count($ids);
        foreach ($ids as $id) {
            $this->tables[$table][$id]['seen_run'] = $run;
        }
    }

    public function deleteNotSeen(string $table, int $run): int
    {
        $this->calls[] = "deleteNotSeen {$table}";
        $before = count($this->tables[$table]);
        $this->tables[$table] = array_filter($this->tables[$table], static fn(array $row): bool => $row['seen_run'] === $run);

        return $before - count($this->tables[$table]);
    }

    public function delete(string $table, array $ids): int
    {
        $this->calls[] = "delete {$table} " . count($ids);
        $deleted = count(array_intersect_key($this->tables[$table], array_flip($ids)));
        $this->tables[$table] = array_diff_key($this->tables[$table], array_flip($ids));

        return $deleted;
    }

    public function setBoost(array $boostById): void
    {
        $this->calls[] = 'setBoost ' . count($boostById);
        foreach ($this->tables['settlements'] as $id => $row) {
            $this->tables['settlements'][$id]['boost'] = $boostById[$id] ?? 0;
        }
    }
}
