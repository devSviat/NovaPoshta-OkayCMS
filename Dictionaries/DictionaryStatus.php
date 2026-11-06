<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Storage\StateStorage;

/** What the dictionaries hold and how the last run went; kept as JSON in module state. */
final class DictionaryStatus
{
    private const string STATE = 'dict_status';

    /** Unix time the stored divisions are current to (base time or last applied delta's `till`). */
    public ?int $divisionsTill = null;
    public ?int $settlementsTime = null;
    public ?int $boostAt = null;
    /** Whether the stored rows carry English names. */
    public bool $english = false;
    public ?int $requestedAt = null;
    public ?int $runningSince = null;
    /** @var array{started:int, finished:int, ok:bool, kind:string, counts:array, error:?string, boost_error:?string}|null */
    public ?array $lastRun = null;

    public static function load(StateStorage $state): self
    {
        $status = new self();
        $data = json_decode((string)$state->get(self::STATE), true);
        if (!is_array($data)) {
            return $status;
        }
        foreach (['divisionsTill', 'settlementsTime', 'boostAt', 'requestedAt', 'runningSince'] as $field) {
            $status->$field = is_int($data[$field] ?? null) ? $data[$field] : null;
        }
        $status->english = ($data['english'] ?? false) === true;
        $status->lastRun = is_array($data['lastRun'] ?? null) ? $data['lastRun'] : null;

        return $status;
    }

    public function save(StateStorage $state): void
    {
        $state->set(self::STATE, json_encode([
            'divisionsTill' => $this->divisionsTill,
            'settlementsTime' => $this->settlementsTime,
            'boostAt' => $this->boostAt,
            'english' => $this->english,
            'requestedAt' => $this->requestedAt,
            'runningSince' => $this->runningSince,
            'lastRun' => $this->lastRun,
        ], JSON_UNESCAPED_UNICODE));
    }
}
