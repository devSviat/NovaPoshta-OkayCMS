<?php

namespace Modules\Sviat\NovaPoshta\Api;

require_once __DIR__ . '/../Support/FakeTransport.php';
require_once __DIR__ . '/../Support/InMemoryState.php';
require_once __DIR__ . '/../Support/ApiFixtures.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Modules\Sviat\NovaPoshta\Support\FakeTransport;
use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpResponse;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NewApiClientTest extends TestCase
{
    private const int EXP = 2_000_000_000;

    private FakeTransport $http;
    private ?string $key = F::KEY;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
    }

    private function client(): NewApiClient
    {
        $jwt = new JwtProvider($this->http, new InMemoryState(), F::apiKey($this, $this->key), static fn(): int => self::EXP - 3600);

        return new NewApiClient($this->http, $jwt, static function (): void {
        });
    }

    private function kindOf(callable $call): NpErrorKind
    {
        try {
            $call();
        } catch (NpApiException $e) {
            self::assertStringNotContainsString(F::KEY, $e->getMessage());
            self::assertStringNotContainsString(F::jwt(self::EXP), $e->getMessage());

            return $e->kind;
        }
        self::fail('expected NpApiException');
    }

    public function testGetReturnsDecodedBody(): void
    {
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)])->json(200, ['items' => [['id' => 1]]]);

        self::assertSame(['items' => [['id' => 1]]], $this->client()->get('/divisions', ['limit' => 1, 'countryCodes[]' => 'UA']));
    }

    public function testAuthorizationHeaderAndNoKeyInUrl(): void
    {
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)])->json(200, []);
        $this->client()->get('/divisions', ['limit' => 1]);

        $request = $this->http->requests()[1];
        self::assertSame('https://api.novaposhta.ua/v.1.0/divisions?limit=1', $request->url);
        self::assertSame(F::jwt(self::EXP), $request->headers['Authorization']);
        self::assertSame('uk', $request->headers['Accept-Language']);
        self::assertStringNotContainsString(F::KEY, $request->url);
    }

    public function testPostSendsJson(): void
    {
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)])->json(201, ['id' => 'x']);

        self::assertSame(['id' => 'x'], $this->client()->post('/shipments/calculations', ['a' => 'б']));
        $request = $this->http->requests()[1];
        self::assertSame('POST', $request->method);
        self::assertSame('{"a":"б"}', $request->body);
        self::assertSame('application/json', $request->headers['Content-Type']);
    }

    public function testUnauthorizedRefreshesOnce(): void
    {
        $this->http
            ->json(200, ['jwt' => F::jwt(self::EXP, 'old')])
            ->json(401, ['errors' => ['errorMessage' => 'Invalid token']])
            ->json(200, ['jwt' => F::jwt(self::EXP, 'new')])
            ->json(200, ['ok' => true]);

        self::assertSame(['ok' => true], $this->client()->get('/divisions'));
        self::assertCount(4, $this->http->requests());
        self::assertSame(F::jwt(self::EXP, 'new'), $this->http->requests()[3]->headers['Authorization']);
    }

    public function testSecondUnauthorizedStops(): void
    {
        $this->http
            ->json(200, ['jwt' => F::jwt(self::EXP)])
            ->json(401, ['errors' => ['errorMessage' => 'Invalid token']])
            ->json(200, ['jwt' => F::jwt(self::EXP)])
            ->json(401, ['errors' => ['errorMessage' => 'Invalid token']]);

        self::assertSame(NpErrorKind::Unauthorized, $this->kindOf(fn() => $this->client()->get('/divisions')));
        self::assertCount(4, $this->http->requests());
    }

    public static function failures(): array
    {
        return [
            '429' => [new HttpResponse(429, '{"errors":{"errorMessage":"Too many requests"}}'), NpErrorKind::RateLimited],
            '500' => [new HttpResponse(500, 'oops'), NpErrorKind::Unavailable],
            'html' => [new HttpResponse(200, '<html>'), NpErrorKind::Malformed],
            '422' => [new HttpResponse(422, '{"errors":{"weight":"The weight must be a number."}}'), NpErrorKind::Rejected],
        ];
    }

    #[DataProvider('failures')]
    public function testFailureKinds(HttpResponse $answer, NpErrorKind $kind): void
    {
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)])->push($answer, $answer);

        self::assertSame($kind, $this->kindOf(fn() => $this->client()->post('/shipments/calculations', [])));
    }

    public function testRejectedKeepsNovaPoshtaTexts(): void
    {
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)])->json(422, ['errors' => ['weight' => 'The weight must be a number.']]);

        try {
            $this->client()->post('/shipments/calculations', []);
            self::fail('expected NpApiException');
        } catch (NpApiException $e) {
            self::assertSame(['weight: The weight must be a number.'], $e->npMessages);
        }
    }

    public function testGetIsRetriedOnceWhenUnavailable(): void
    {
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)])
            ->push(new TransportException('timeout'))
            ->json(200, ['ok' => true]);

        self::assertSame(['ok' => true], $this->client()->get('/divisions'));
    }

    public function testPostIsNeverRetried(): void
    {
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)])->push(new TransportException('timeout'));

        self::assertSame(NpErrorKind::Unavailable, $this->kindOf(fn() => $this->client()->post('/shipments', [])));
        self::assertCount(2, $this->http->requests());
    }
}
