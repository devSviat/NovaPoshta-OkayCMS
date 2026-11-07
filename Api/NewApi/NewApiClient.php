<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api\NewApi;

use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpRequest;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpTransport;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;

/** REST API v.1.0 for Ukraine (dictionaries, calculation, tracking, branch shipments). */
final class NewApiClient
{
    public const string BASE_URL = 'https://api.novaposhta.ua/v.1.0';

    private readonly \Closure $sleep;
    private int $timeoutSeconds = 20;
    private bool $retries = true;

    public function __construct(
        private readonly HttpTransport $http,
        private readonly JwtProvider $jwt,
        ?\Closure $sleep = null,
    ) {
        $this->sleep = $sleep ?? static fn() => sleep(1);
    }

    /** Short timeout and no repeats: for answers a customer waits for in the cart. */
    public function quick(int $timeoutSeconds): self
    {
        $client = clone $this;
        $client->timeoutSeconds = $timeoutSeconds;
        $client->retries = false;

        return $client;
    }

    public function get(string $path, array $query = []): array
    {
        $url = self::BASE_URL . $path . ($query === [] ? '' : '?' . http_build_query($query));

        return $this->send('GET', $url, null);
    }

    public function post(string $path, array $body): array
    {
        return $this->send('POST', self::BASE_URL . $path, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** Never repeated, like POST: the first request may already have deleted it. */
    public function delete(string $path): array
    {
        return $this->send('DELETE', self::BASE_URL . $path, null);
    }

    private function send(string $method, string $url, ?string $body): array
    {
        // A POST may have reached Nova Poshta before the connection broke: repeating it could create a second shipment.
        $retriesLeft = $method === 'GET' && $this->retries ? 1 : 0;
        $tokenRenewed = false;

        while (true) {
            $jwt = $this->jwt->token($this->timeoutSeconds);
            try {
                return $this->once($method, $url, $body, $jwt);
            } catch (NpApiException $e) {
                if ($e->kind === NpErrorKind::Unauthorized && !$tokenRenewed) {
                    $tokenRenewed = true;
                    $this->jwt->invalidate();
                    continue;
                }
                if ($e->kind === NpErrorKind::Unavailable && $retriesLeft-- > 0) {
                    ($this->sleep)();
                    continue;
                }
                throw $e;
            }
        }
    }

    private function once(string $method, string $url, ?string $body, string $jwt): array
    {
        $headers = ['Authorization' => $jwt, 'Accept' => 'application/json', 'Accept-Language' => 'uk'];
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        try {
            $response = $this->http->send(new HttpRequest($method, $url, $headers, $body, $this->timeoutSeconds));
        } catch (TransportException $e) {
            throw NpApiException::fromNp(NpErrorKind::Unavailable, [$e->getMessage()], [], [$jwt]);
        }

        $decoded = $response->json();
        if ($response->status >= 200 && $response->status < 300 && $decoded !== null) {
            return $decoded;
        }

        throw NewApiErrors::fromResponse($response, [$jwt]);
    }
}
