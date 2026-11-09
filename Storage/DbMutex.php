<?php

namespace Okay\Modules\Sviat\NovaPoshta\Storage;

use Okay\Core\Database;
use Okay\Core\QueryFactory;

/**
 * GET_LOCK бази: атомарне, і сервер сам знімає блокування, коли з'єднання закривається — завислий чи
 * вбитий процес не тримає його вічно.
 */
final class DbMutex implements Mutex
{
    /** MariaDB обмежує назву 64 символами; префікс відділяє імена модуля від чужих. */
    private const string PREFIX = 'sviat_np_';

    public function __construct(
        private readonly Database $db,
        private readonly QueryFactory $queryFactory,
    ) {
    }

    public function acquire(string $name, int $waitSeconds = 0): bool
    {
        $sql = $this->queryFactory->newSqlQuery()->setStatement('SELECT GET_LOCK(:name, :wait) AS locked')
            ->bindValues(['name' => self::PREFIX . $name, 'wait' => max(0, $waitSeconds)]);

        return $this->db->query($sql) !== false && (int)$this->db->result('locked') === 1;
    }

    public function release(string $name): void
    {
        $sql = $this->queryFactory->newSqlQuery()->setStatement('SELECT RELEASE_LOCK(:name)')
            ->bindValues(['name' => self::PREFIX . $name]);
        $this->db->query($sql);
    }
}
