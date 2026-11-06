<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api\NewApi;

use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpRequest;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpTransport;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Storage\StateStorage;

/** JWT for the new API: lives about an hour, kept in module state and bound to the key it was issued for. */
final class JwtProvider
{
    public const string AUTHORIZATION_URL = 'https://api.novaposhta.ua/v.1.0/clients/authorization';

    /** Renew this long before `exp`, so a token never expires between reading and use. */
    private const int REFRESH_MARGIN = 300;
    /** Used when a token carries no readable `exp`; NP tokens live 3600 s. */
    private const int FALLBACK_TTL = 3000;

    private readonly \Closure $now;

    public function __construct(
        private readonly HttpTransport $http,
        private readonly StateStorage $state,
        private readonly ApiKey $apiKey,
        ?\Closure $now = null,
    ) {
        $this->now = $now ?? time(...);
    }

    /** @param int $timeoutSeconds for a token that has to be issued now */
    public function token(int $timeoutSeconds = 20): string
    {
        $key = $this->apiKey->value()
            ?? throw NpApiException::fromNp(NpErrorKind::InvalidKey, ['API key is not set']);

        $jwt = $this->state->get('jwt');
        if ($jwt !== null
            && $this->state->get('jwt_key') === ApiKey::fingerprint($key)
            && (int)$this->state->get('jwt_expires_at') - ($this->now)() >= self::REFRESH_MARGIN
        ) {
            return $jwt;
        }

        $jwt = self::issue($this->http, $key, $timeoutSeconds);
        $this->store($key, $jwt);

        return $jwt;
    }

    /** Keeps a token issued elsewhere (the key check) so the next call does not ask for another. */
    public function store(string $apiKey, string $jwt): void
    {
        $this->state->set('jwt', $jwt);
        $this->state->set('jwt_key', ApiKey::fingerprint($apiKey));
        $this->state->set('jwt_expires_at', (string)(self::expiresAt($jwt) ?? ($this->now)() + self::FALLBACK_TTL));
    }

    public function invalidate(): void
    {
        $this->state->set('jwt', null);
        $this->state->set('jwt_expires_at', null);
    }

    /** Asks Nova Poshta for a token right now, without the cache. */
    public static function issue(HttpTransport $http, string $apiKey, int $timeoutSeconds = 20): string
    {
        $secrets = [$apiKey];
        try {
            $response = $http->send(new HttpRequest('GET', self::AUTHORIZATION_URL . '?' . http_build_query(['apiKey' => $apiKey]), [
                'Accept' => 'application/json',
            ], null, $timeoutSeconds));
        } catch (TransportException $e) {
            throw NpApiException::fromNp(NpErrorKind::Unavailable, [$e->getMessage()], [], $secrets);
        }

        $jwt = $response->json()['jwt'] ?? null;
        if ($response->status === 200 && is_string($jwt) && $jwt !== '') {
            return $jwt;
        }
        if ($response->status === 200) {
            throw NpApiException::fromNp(NpErrorKind::Malformed, ['No token in the authorization answer'], [], $secrets);
        }

        throw NewApiErrors::fromResponse($response, $secrets, isAuthorization: true);
    }

    public static function expiresAt(string $jwt): ?int
    {
        $payload = explode('.', $jwt)[1] ?? '';
        $claims = json_decode((string)base64_decode(strtr($payload, '-_', '+/'), true), true);

        return is_array($claims) && is_int($claims['exp'] ?? null) ? $claims['exp'] : null;
    }
}
