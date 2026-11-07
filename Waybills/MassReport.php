<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** What the mass action did to each order; kept in the session until the list shows it. */
final readonly class MassReport
{
    private const array KINDS = ['created', 'skipped', 'failed'];

    /**
     * @param list<array{orderId: int, kind: 'created'|'skipped'|'failed', key: ?string, value: ?string}> $rows
     *     key: a language key without the module prefix; value: the waybill number or Nova Poshta's text
     * @param int $untouched selected orders left for the next run (limit or time budget)
     */
    public function __construct(
        public array $rows,
        public int $untouched,
    ) {
    }

    public function created(): int
    {
        return count(array_filter($this->rows, static fn(array $row): bool => $row['kind'] === 'created'));
    }

    public function processed(): int
    {
        return count($this->rows);
    }

    public function toArray(): array
    {
        return ['rows' => $this->rows, 'untouched' => $this->untouched];
    }

    /** @return ?self null for anything this class did not write */
    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data) || !is_array($data['rows'] ?? null) || !is_int($data['untouched'] ?? null)) {
            return null;
        }
        $rows = [];
        foreach ($data['rows'] as $row) {
            if (!is_array($row) || !is_int($row['orderId'] ?? null) || !in_array($row['kind'] ?? null, self::KINDS, true)
                || !(is_string($row['key'] ?? null) || ($row['key'] ?? null) === null)
                || !(is_string($row['value'] ?? null) || ($row['value'] ?? null) === null)) {
                return null;
            }
            $rows[] = ['orderId' => $row['orderId'], 'kind' => $row['kind'], 'key' => $row['key'], 'value' => $row['value']];
        }

        return new self($rows, $data['untouched']);
    }
}
