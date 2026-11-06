<?php

namespace Modules\Sviat\NovaPoshta\Api;

require_once __DIR__ . '/../Support/FakeTransport.php';
require_once __DIR__ . '/../Support/ApiFixtures.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Modules\Sviat\NovaPoshta\Support\FakeTransport;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpResponse;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OldApiClientTest extends TestCase
{
    private FakeTransport $http;
    private ?string $key = F::KEY;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
    }

    private function client(): OldApiClient
    {
        return new OldApiClient($this->http, F::apiKey($this, $this->key), static function (): void {
        });
    }

    private static function fail200(array $errors, array $codes = []): array
    {
        return ['success' => false, 'data' => [], 'errors' => $errors, 'warnings' => [], 'errorCodes' => $codes];
    }

    private function error(callable $call): NpApiException
    {
        try {
            $call();
        } catch (NpApiException $e) {
            self::assertStringNotContainsString(F::KEY, $e->getMessage());

            return $e;
        }
        self::fail('expected NpApiException');
    }

    public function testReturnsData(): void
    {
        $this->http->json(200, ['success' => true, 'data' => [['Ref' => 'r1']], 'errors' => []]);

        self::assertSame([['Ref' => 'r1']], $this->client()->call('Counterparty', 'getCounterparties', ['Page' => 1]));

        $request = $this->http->requests()[0];
        self::assertSame('POST', $request->method);
        self::assertSame('https://api.novaposhta.ua/v2.0/json/', $request->url);
        self::assertSame(
            ['apiKey' => F::KEY, 'modelName' => 'Counterparty', 'calledMethod' => 'getCounterparties', 'methodProperties' => ['Page' => 1]],
            json_decode($request->body, true),
        );
    }

    public function testEmptyPropertiesAreSentAsObject(): void
    {
        $this->http->json(200, ['success' => true, 'data' => []]);
        $this->client()->call('Common', 'getCargoTypes');

        self::assertStringContainsString('"methodProperties":{}', $this->http->requests()[0]->body);
    }

    public function testWithKeyUsesTheGivenKey(): void
    {
        $this->http->json(200, ['success' => true, 'data' => []]);
        $this->client()->withKey(F::OTHER_KEY)->call('Common', 'getCargoTypes');

        self::assertSame(F::OTHER_KEY, json_decode($this->http->requests()[0]->body, true)['apiKey']);
    }

    public function testKeyErrorIsInvalidKey(): void
    {
        $this->http->json(200, self::fail200(['API key expired'], ['20000201204']));

        self::assertSame(NpErrorKind::InvalidKey, $this->error(fn() => $this->client()->call('Counterparty', 'getCounterparties'))->kind);
    }

    public function testMissingKeyMakesNoRequest(): void
    {
        $this->key = null;

        self::assertSame(NpErrorKind::InvalidKey, $this->error(fn() => $this->client()->call('Common', 'getCargoTypes'))->kind);
        self::assertCount(0, $this->http->requests());
    }

    public function testBusinessErrorKeepsCodes(): void
    {
        $this->http->json(200, self::fail200(['RecipientCityName not selected'], ['20000900746']));

        $e = $this->error(fn() => $this->client()->call('InternetDocument', 'getDocumentPrice'));
        self::assertSame(NpErrorKind::Rejected, $e->kind);
        self::assertSame(['20000900746'], $e->codes);
        self::assertSame(['RecipientCityName not selected'], $e->npMessages);
    }

    public function testSuccessFalseWithEmptyDataIsAnError(): void
    {
        $this->http->json(200, ['success' => false, 'data' => []]);

        self::assertSame(NpErrorKind::Rejected, $this->error(fn() => $this->client()->call('Common', 'getCargoTypes'))->kind);
    }

    public static function broken(): array
    {
        return [
            'html' => [new HttpResponse(200, '<html>'), NpErrorKind::Malformed],
            'no success flag' => [new HttpResponse(200, '{"data":[]}'), NpErrorKind::Malformed],
            '502' => [new HttpResponse(502, ''), NpErrorKind::Unavailable],
        ];
    }

    #[DataProvider('broken')]
    public function testBrokenAnswers(HttpResponse $answer, NpErrorKind $kind): void
    {
        $this->http->push($answer, $answer);

        self::assertSame($kind, $this->error(fn() => $this->client()->call('Common', 'getCargoTypes'))->kind);
    }

    public static function mutations(): array
    {
        return [['save'], ['Update'], ['DELETE']];
    }

    #[DataProvider('mutations')]
    public function testMutationIsNeverRetried(string $method): void
    {
        $this->http->push(new TransportException('timeout'), new TransportException('timeout'));

        self::assertSame(NpErrorKind::Unavailable, $this->error(fn() => $this->client()->call('InternetDocument', $method))->kind);
        self::assertCount(1, $this->http->requests());
    }

    public function testMutationIsNotRetriedOnRateLimitEither(): void
    {
        $this->http->json(200, self::fail200(['To many requests']));

        self::assertSame(NpErrorKind::RateLimited, $this->error(fn() => $this->client()->call('InternetDocument', 'save'))->kind);
        self::assertCount(1, $this->http->requests());
    }

    public function testReadIsRetriedOnceOnRateLimit(): void
    {
        $this->http->json(200, self::fail200(['To many requests']))->json(200, ['success' => true, 'data' => [1]]);

        self::assertSame([1], $this->client()->call('Address', 'getWarehouses'));
        self::assertCount(2, $this->http->requests());
    }

    public function testReadIsRetriedOnlyOnce(): void
    {
        $this->http->push(new TransportException('a'), new TransportException('b'), new TransportException('c'));

        self::assertSame(NpErrorKind::Unavailable, $this->error(fn() => $this->client()->call('Address', 'getWarehouses'))->kind);
        self::assertCount(2, $this->http->requests());
    }
}
