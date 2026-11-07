<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** What the read-back of a created waybill showed. */
final readonly class AuditResult
{
    /**
     * @param 'verified'|'mismatch'|'unverified' $status
     * @param list<array{kind: string, expected: string, actual: string}> $findings
     * @param list<string> $unchecked ordered services Nova Poshta does not report back (lifting)
     */
    public function __construct(
        public string $status,
        public array $findings = [],
        public array $unchecked = [],
    ) {
    }

    public function toArray(): array
    {
        return ['status' => $this->status, 'findings' => $this->findings, 'unchecked' => $this->unchecked];
    }

    public static function fromArray(array $data): self
    {
        $status = in_array($data['status'] ?? null, ['verified', 'mismatch', 'unverified'], true) ? $data['status'] : 'unverified';
        $findings = [];
        foreach ((array)($data['findings'] ?? []) as $finding) {
            if (is_array($finding)) {
                $findings[] = ['kind' => (string)($finding['kind'] ?? ''), 'expected' => (string)($finding['expected'] ?? ''), 'actual' => (string)($finding['actual'] ?? '')];
            }
        }

        return new self($status, $findings, array_values(array_map('strval', (array)($data['unchecked'] ?? []))));
    }
}
