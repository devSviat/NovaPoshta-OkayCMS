<?php

namespace Okay\Modules\Sviat\NovaPoshta\KeyCheck;

use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Storage\StateStorage;

/**
 * Nova Poshta refused the saved key while the module worked (cart, tracking, waybills): the key
 * block shows it at once. NP gives no expiry date, so this is the first sign of an expired key.
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
