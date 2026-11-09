<?php

namespace Okay\Modules\Sviat\NovaPoshta\KeyCheck;

use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Storage\StateStorage;

/**
 * Нова Пошта відхилила збережений ключ під час роботи модуля (кошик, трекінг, накладні): блок
 * ключа одразу це показує. НП не дає дати закінчення дії, тож це перша ознака простроченого ключа.
 */
final class KeyRejections
{
    private readonly \Closure $now;

    public function __construct(
        private readonly StateStorage $state,
        private readonly ApiKey $apiKey,
        ?\Closure $now = null,
    ) {
        $this->now = $now ?? time(...);
    }

    /** @param 'new'|'old' $api */
    public function record(string $api, string $key): void
    {
        $saved = $this->apiKey->value();
        if ($saved === null || ApiKey::fingerprint($saved) !== ApiKey::fingerprint($key)) {
            return;
        }
        $last = KeyCheckResult::fromJson((string)$this->state->get(KeyChecker::STATE));
        $new = $last->newApi ?? KeyAccess::Unreachable;
        $old = $last->oldApi ?? KeyAccess::Unreachable;
        if (($api === 'new' ? $new : $old) === KeyAccess::InvalidKey) {
            return;
        }
        $at = new \DateTimeImmutable('@' . ($this->now)());
        $result = $api === 'new' ? new KeyCheckResult(KeyAccess::InvalidKey, $old, $at) : new KeyCheckResult($new, KeyAccess::InvalidKey, $at);
        $this->state->set(KeyChecker::STATE, $result->toJson());
    }
}
