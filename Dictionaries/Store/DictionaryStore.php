<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store;

/**
 * Масові записи рядків довідників. $table — 'divisions' або 'settlements'.
 * Кожен метод кидає виняток на невдалому запиті: тихо провалений запис, а за ним
 * deleteNotSeen() стерли б рядки, які ще дійсні.
 */
interface DictionaryStore
{
    public const string DIVISIONS = 'divisions';
    public const string SETTLEMENTS = 'settlements';

    /** @return array<int, string> id → row_hash */
    public function hashes(string $table): array;

    /** @param list<array> $rows вставлені або замінені; `boost` населених пунктів зберігається */
    public function upsert(string $table, array $rows, int $run): void;

    /** Позначає незмінені рядки як присутні в запуску $run. @param list<int> $ids */
    public function touch(string $table, array $ids, int $run): void;

    /** Видаляє рядки, яких повний запуск $run не побачив; викликати лише після того, як запуск прочитав усе. */
    public function deleteNotSeen(string $table, int $run): int;

    /** @param list<int> $ids */
    public function delete(string $table, array $ids): int;

    /** Позначає населені пункти, що мають робочий пункт видачі (кошик шукає лише серед них). */
    public function refreshPickup(): void;

    /** @param array<int, int> $boostById населені пункти поза списком отримують 0 */
    public function setBoost(array $boostById): void;
}
