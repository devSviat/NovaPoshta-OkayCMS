<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api\OldApi;

use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpRequest;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpTransport;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyRejections;

/** JSON API v2.0: лише те, чого новий API ще не вміє (післяплата, контроль оплати, адресні відправлення). */
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

    /** Клієнт для ще не збереженого ключа (перевірка ключа перед збереженням). */
    public function withKey(string $apiKey): self
    {
        $client = clone $this;
        $client->fixedKey = $apiKey;

        return $client;
    }

    /**
     * Короткий тайм-аут для запитів, на які хтось чекає в браузері. Мертвий сервер повторно
     * не опитується; "To many requests" Нової Пошти на читанні перечікується один раз (минає протягом
     * секунди, а два читання з інтервалом 0.4 s уже можуть його спричинити — виміряно 06.11.2026).
     */
    public function quick(int $timeoutSeconds): self
    {
        $client = clone $this;
        $client->retries = false;
        $client->quickMode = true;
        $client->timeoutSeconds = $timeoutSeconds;

        return $client;
    }

    /** @return array частина `data` відповіді */
    public function call(string $model, string $method, array $properties = []): array
    {
        return $this->callWithWarnings($model, $method, $properties)['data'];
    }

    /**
     * Як call(), але з попередженнями Нової Пошти: збережена накладна може повернутися зміненою
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

        // Повторюються лише читання. Будь-що інше може змінити дані в Новій Пошті (save, delete,
        // insertDocuments…): після тайм-ауту це вже могло статися, і повтор зробив би це двічі.
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
            // "To many requests" — це написання самої НП.
            stripos($joined, 'to many requests') !== false, stripos($joined, 'too many requests') !== false => NpErrorKind::RateLimited,
            default => NpErrorKind::Rejected,
        };

        if ($kind === NpErrorKind::InvalidKey && $this->fixedKey === null) {
            $this->rejections?->record('old', $key);
        }

        throw NpApiException::fromNp($kind, $texts ?: ['Nova Poshta refused the request'], $codes, $secrets);
    }
}
