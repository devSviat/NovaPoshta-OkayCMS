<?php

namespace Okay\Modules\Sviat\NovaPoshta\KeyCheck;

use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpTransport;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Storage\StateStorage;

/** Checks a key against both Nova Poshta APIs before the manager relies on it. */
final class KeyChecker
{
    private const string STATE = 'key_check';

    /** Both checks run while the manager waits for the page; keep them well under proxy timeouts (60 s). */
    public const int TIMEOUT_SECONDS = 8;

    private readonly \Closure $now;

    public function __construct(
        private readonly HttpTransport $http,
        private readonly OldApiClient $oldApi,
        private readonly JwtProvider $jwt,
        private readonly StateStorage $state,
        ?\Closure $now = null,
    ) {
        $this->now = $now ?? time(...);
    }

    public function check(string $rawKey): KeyCheckResult
    {
        $key = ApiKey::normalize($rawKey);
        $at = new \DateTimeImmutable('@' . ($this->now)());

        return new KeyCheckResult($this->checkNewApi($key), $this->checkOldApi($key), $at);
    }

    public function remember(KeyCheckResult $result): void
    {
        $this->state->set(self::STATE, $result->toJson());
    }

    public function lastResult(): ?KeyCheckResult
    {
        $json = $this->state->get(self::STATE);

        return $json === null ? null : KeyCheckResult::fromJson($json);
    }

    private function checkNewApi(string $key): KeyAccess
    {
        try {
            $this->jwt->store($key, JwtProvider::issue($this->http, $key, self::TIMEOUT_SECONDS));

            return KeyAccess::Ok;
        } catch (NpApiException $e) {
            return self::access($e);
        }
    }

    private function checkOldApi(string $key): KeyAccess
    {
        try {
            // Needs a valid key and touches nothing: the shop's own sender counterparties.
            $this->oldApi->withKey($key)->quick(self::TIMEOUT_SECONDS)->call('Counterparty', 'getCounterparties', ['CounterpartyProperty' => 'Sender', 'Page' => 1]);

            return KeyAccess::Ok;
        } catch (NpApiException $e) {
            return self::access($e);
        }
    }

    private static function access(NpApiException $e): KeyAccess
    {
        return match ($e->kind) {
            NpErrorKind::InvalidKey => KeyAccess::InvalidKey,
            NpErrorKind::Unauthorized, NpErrorKind::Rejected => KeyAccess::Denied,
            NpErrorKind::Unavailable, NpErrorKind::RateLimited, NpErrorKind::Malformed => KeyAccess::Unreachable,
        };
    }
}
