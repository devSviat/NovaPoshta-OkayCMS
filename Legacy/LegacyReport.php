<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Counts per step and the rows that need the manager; kept in the module state between ticks. */
final class LegacyReport
{
    public const int FAILS_MAX = 200;
    private const array KINDS = ['done', 'existed', 'failed'];

    /** @var array<string, array{done:int, existed:int, failed:int}> */
    private array $counts = [];
    /** @var list<array{step:string, order:?int, key:string, name:?string}> */
    private array $fails = [];
    private int $moreFails = 0;

    public function add(string $step, string $kind): void
    {
        $this->counts[$step] ??= array_fill_keys(self::KINDS, 0);
        $this->counts[$step][$kind]++;
    }

    /** Counted as failed; the row itself may still be carried over with its names. */
    public function fail(string $step, ?int $orderId, string $key, ?string $name = null): void
    {
        $this->add($step, 'failed');
        if (count($this->fails) < self::FAILS_MAX) {
            $this->fails[] = ['step' => $step, 'order' => $orderId, 'key' => $key, 'name' => $name !== null ? mb_substr($name, 0, 255) : null];
        } else {
            $this->moreFails++;
        }
    }

    /** @return array{done:int, existed:int, failed:int} */
    public function counts(string $step): array
    {
        return $this->counts[$step] ?? array_fill_keys(self::KINDS, 0);
    }

    /** @return list<array{step:string, order:?int, key:string, name:?string}> */
    public function fails(): array
    {
        return $this->fails;
    }

    public function moreFails(): int
    {
        return $this->moreFails;
    }

    public function toArray(): array
    {
        return ['counts' => $this->counts, 'fails' => $this->fails, 'more' => $this->moreFails];
    }

    public static function fromArray(mixed $data): self
    {
        $report = new self();
        if (!is_array($data)) {
            return $report;
        }
        foreach (is_array($data['counts'] ?? null) ? $data['counts'] : [] as $step => $counts) {
            if (is_string($step) && is_array($counts)) {
                foreach (self::KINDS as $kind) {
                    $report->counts[$step][$kind] = max(0, (int)($counts[$kind] ?? 0));
                }
            }
        }
        foreach (is_array($data['fails'] ?? null) ? $data['fails'] : [] as $fail) {
            if (is_array($fail) && is_string($fail['step'] ?? null) && is_string($fail['key'] ?? null) && count($report->fails) < self::FAILS_MAX) {
                $report->fails[] = [
                    'step' => $fail['step'],
                    'order' => isset($fail['order']) && is_numeric($fail['order']) ? (int)$fail['order'] : null,
                    'key' => $fail['key'],
                    'name' => is_string($fail['name'] ?? null) ? $fail['name'] : null,
                ];
            }
        }
        $report->moreFails = max(0, (int)($data['more'] ?? 0));

        return $report;
    }
}
