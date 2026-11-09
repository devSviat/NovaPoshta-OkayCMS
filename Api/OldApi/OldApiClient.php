<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api\OldApi;

use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpRequest;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpTransport;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyRejections;

/** JSON API v2.0: only what the new API cannot do yet (COD, payment control, address shipments). */
final class OldApiClient
{
    public const string URL = 'https://api.novaposhta.ua/v2.0/json/';

    private const string INVALID_KEY_CODE = '20000201204';

    private readonly \Closure $sleep;
    private ?string $fixedKey = null;
    private bool $retries = true;
    private bool $quickMode = false;
    private int $timeoutSeconds = 20;

    public function __construct(
        private readonly HttpTransport $http,
        private readonly ApiKey $apiKey,
        ?\Closure $sleep = null,
        private readonly ?KeyRejections $rejections = null,
    ) {
        $this->sleep = $sleep ?? static fn() => sleep(1);
    }

    /** A client for a key that is not saved yet (checking a key before storing it). */
    public function withKey(string $apiKey): self
    {
        $client = clone $this;
        $client->fixedKey = $apiKey;

        return $client;
    }

    /**
     * Short timeout, for requests someone waits for in the browser. A dead server is not asked
     * again; Nova Poshta's "To many requests" on a read is waited out once (it clears within a
     * second, while two reads 0.4 s apart can already trip it — measured 06.11.2026).
     */
    public function quick(int $timeoutSeconds): self
    {
        $client = clone $this;
        $client->retries = false;
        $client->quickMode = true;
        $client->timeoutSeconds = $timeoutSeconds;

        return $client;
    }

    /** @return array the `data` part of the answer */
    public function call(string $model, string $method, array $properties = []): array
    {
        return $this->callWithWarnings($model, $method, $properties)['data'];
    }

    /**
     * Like call(), with Nova Poshta's warnings: a saved waybill can come back changed
     * ("CargoType is changed to Cargo").
     *
     * @return array{data: array, warnings: list<string>}
     */
    public function callWithWarnings(string $model, string $method, array $properties = []): array
    {
        $key = $this->fixedKey ?? $this->apiKey->value()
            ?? throw NpApiException::fromNp(NpErrorKind::InvalidKey, ['API key is not set']);

        $body = json_encode([
            'apiKey' => $key,
            'modelName' => $model,
            'calledMethod' => $method,
            'methodProperties' => $properties === [] ? new \stdClass() : $properties,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        // Only reads are repeated. Anything else may change data at Nova Poshta (save, delete,
        // insertDocuments…): after a timeout it may already be done, and a repeat would do it twice.
        $isRead = str_starts_with(strtolower($method), 'get');
        $retriesLeft = $isRead && $this->retries ? 1 : 0;
        $rateWaits = 1;

        while (true) {
            try {
                return $this->once($body, $key);
            } catch (NpApiException $e) {
                $transient = $e->kind === NpErrorKind::RateLimited || $e->kind === NpErrorKind::Unavailable;
                $waitOut = $this->quickMode && $isRead && $e->kind === NpErrorKind::RateLimited && $rateWaits-- > 0;
                if (($transient && $retriesLeft-- > 0) || $waitOut) {
                    ($this->sleep)();
                    continue;
                }
                throw $e;
            }
        }
    }

    private function once(string $body, string $key): array
    {
        $secrets = [$key];
        try {
            $response = $this->http->send(new HttpRequest('POST', self::URL, ['Content-Type' => 'application/json'], $body, $this->timeoutSeconds));
        } catch (TransportException $e) {
            throw NpApiException::fromNp(NpErrorKind::Unavailable, [$e->getMessage()], [], $secrets);
        }

        if ($response->status >= 500) {
            throw NpApiException::fromNp(NpErrorKind::Unavailable, ["HTTP {$response->status}"], [], $secrets);
        }
        if ($response->status === 429) {
            throw NpApiException::fromNp(NpErrorKind::RateLimited, ['HTTP 429'], [], $secrets);
        }

        $answer = $response->json();
        if ($response->status !== 200 || $answer === null || !array_key_exists('success', $answer)) {
            throw NpApiException::fromNp(NpErrorKind::Malformed, ["HTTP {$response->status}"], [], $secrets);
        }

        if ($answer['success'] === true) {
            return ['data' => (array)($answer['data'] ?? []), 'warnings' => array_values(array_map(strval(...), array_filter((array)($answer['warnings'] ?? []), is_scalar(...))))];
        }

        $texts = array_values(array_map(strval(...), (array)($answer['errors'] ?? [])));
        $codes = array_values(array_map(strval(...), (array)($answer['errorCodes'] ?? [])));
        $joined = implode(' ', $texts);

        $kind = match (true) {
            in_array(self::INVALID_KEY_CODE, $codes, true), stripos($joined, 'API key') !== false => NpErrorKind::InvalidKey,
            // "To many requests" is NP's own spelling.
            stripos($joined, 'to many requests') !== false, stripos($joined, 'too many requests') !== false => NpErrorKind::RateLimited,
            default => NpErrorKind::Rejected,
        };

        if ($kind === NpErrorKind::InvalidKey && $this->fixedKey === null) {
            $this->rejections?->record('old', $key);
        }

        throw NpApiException::fromNp($kind, $texts ?: ['Nova Poshta refused the request'], $codes, $secrets);
    }
}
