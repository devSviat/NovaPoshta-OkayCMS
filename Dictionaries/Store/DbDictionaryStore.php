<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store;

use Aura\SqlQuery\QueryInterface;
use Okay\Core\Database;
use Okay\Core\QueryFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;

/**
 * Bulk writes through QueryFactory: per-row Entity writes made the old NP import take
 * ~13 minutes for 55k branches.
 */
final class DbDictionaryStore implements DictionaryStore
{
    public function __construct(
        private readonly Database $db,
        private readonly QueryFactory $queryFactory,
    ) {
    }

    public function hashes(string $table): array
    {
        $select = $this->queryFactory->newSelect()->cols(['id', 'row_hash'])->from(self::table($table));
        $this->run($select);
        $hashes = [];
        foreach ($this->db->results() as $row) {
            $hashes[(int)$row->id] = (string)$row->row_hash;
        }

        return $hashes;
    }

    public function upsert(string $table, array $rows, int $run): void
    {
        if ($rows === []) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $rows = array_map(static fn(array $row): array => $row + ['seen_run' => $run, 'updated_at' => $now], $rows);

        $insert = $this->queryFactory->newInsert()->into(self::table($table))->addRows($rows);
        foreach (array_keys($rows[0]) as $column) {
            if ($column !== 'id') {
                $insert->onDuplicateKeyUpdate($column, "VALUES({$column})");
            }
        }
        $this->run($insert);
    }

    public function touch(string $table, array $ids, int $run): void
    {
        if ($ids === []) {
            return;
        }
        $update = $this->queryFactory->newUpdate()->table(self::table($table))
            ->set('seen_run', ':run')->where('id IN (:ids)')
            ->bindValues(['run' => $run, 'ids' => $ids]);
        $this->run($update);
    }

    public function deleteNotSeen(string $table, int $run): int
    {
        $delete = $this->queryFactory->newDelete()->from(self::table($table))
            ->where('seen_run <> :run')->bindValue('run', $run);
        $this->run($delete);

        return (int)$this->db->affectedRows();
    }

    public function delete(string $table, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }
        $delete = $this->queryFactory->newDelete()->from(self::table($table))
            ->where('id IN (:ids)')->bindValue('ids', $ids);
        $this->run($delete);

        return (int)$this->db->affectedRows();
    }

    public function refreshPickup(): void
    {
        $this->run($this->queryFactory->newUpdate()->table(self::table(self::SETTLEMENTS) . ' AS s')
            ->set('s.has_pickup', 'EXISTS (SELECT 1 FROM ' . self::table(self::DIVISIONS) . " AS d WHERE d.settlement_id = s.id AND d.status = 'Working' AND d.prohibited_issuance = 0)"));
    }

    public function setBoost(array $boostById): void
    {
        $table = self::table(self::SETTLEMENTS);
        $this->run($this->queryFactory->newUpdate()->table($table)->set('boost', '0')->where('boost <> 0'));
        foreach ($boostById as $id => $boost) {
            $this->run($this->queryFactory->newUpdate()->table($table)
                ->set('boost', ':boost')->where('id = :id')
                ->bindValues(['boost' => (int)$boost, 'id' => (int)$id]));
        }
    }

    /** Database::query() logs and swallows SQL errors; a dictionary run must stop instead. */
    private function run(QueryInterface $query): void
    {
        if ($this->db->query($query) === false) {
            throw new \RuntimeException('Nova Poshta dictionary query failed; see the application log');
        }
    }

    private static function table(string $table): string
    {
        return match ($table) {
            self::DIVISIONS => DivisionEntity::getTable(),
            self::SETTLEMENTS => SettlementEntity::getTable(),
        };
    }
}
