<?php

namespace Modules\Sviat\NovaPoshta\Api;

require_once __DIR__ . '/../Support/FakeTransport.php';
require_once __DIR__ . '/../Support/InMemoryState.php';
require_once __DIR__ . '/../Support/ApiFixtures.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Modules\Sviat\NovaPoshta\Support\FakeTransport;
use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use PHPUnit\Framework\TestCase;

class JwtProviderTest extends TestCase
{
    private const int EXP = 2_000_000_000;

    private FakeTransport $http;
    private InMemoryState $state;
    private ?string $key = F::KEY;
    private int $now = self::EXP - 3600;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->state = new InMemoryState();
    }

    private function provider(): JwtProvider
    {
        return new JwtProvider($this->http, $this->state, F::apiKey($this, $this->key), fn(): int => $this->now);
    }

    public function testTokenIsCachedUntilFiveMinutesBeforeExpiry(): void
    {
        $first = F::jwt(self::EXP);
        $second = F::jwt(self::EXP + 3600, 'renewed');
        $this->http->json(200, ['jwt' => $first])->json(200, ['jwt' => $second]);

        self::assertSame($first, $this->provider()->token());
        $this->now = self::EXP - 300;
        self::assertSame($first, $this->provider()->token(), 'a new provider instance reads the stored token');
        self::assertCount(1, $this->http->requests());

        $this->now = self::EXP - 299;
        self::assertSame($second, $this->provider()->token());
        self::assertCount(2, $this->http->requests());
    }

    public function testAuthorizationRequestCarriesTheKey(): void
    {
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)]);
        $this->provider()->token();

        $request = $this->http->requests()[0];
        self::assertSame('GET', $request->method);
        self::assertSame('https://api.novaposhta.ua/v.1.0/clients/authorization?apiKey=' . F::KEY, $request->url);
    }

    public function testTokenOfAnotherKeyIsNotReused(): void
    {
        $this->http->json(200, ['jwt' => F::jwt(self::EXP, 'a')])->json(200, ['jwt' => F::jwt(self::EXP, 'b')]);
        $this->provider()->token();

        $this->key = F::OTHER_KEY;
        self::assertSame(F::jwt(self::EXP, 'b'), $this->provider()->token());
        self::assertStringEndsWith('apiKey=' . F::OTHER_KEY, $this->http->requests()[1]->url);
    }

    public function testStoredTokenDoesNotContainTheKey(): void
    {
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)]);
        $this->provider()->token();

        foreach ($this->state->values as $value) {
            self::assertStringNotContainsString(F::KEY, $value);
        }
    }

    public function testInvalidateForcesANewToken(): void
    {
        $this->http->json(200, ['jwt' => F::jwt(self::EXP, 'a')])->json(200, ['jwt' => F::jwt(self::EXP, 'b')]);
        $provider = $this->provider();
        $provider->token();
        $provider->invalidate();

        self::assertSame(F::jwt(self::EXP, 'b'), $provider->token());
    }

    public function testExpiredKeyGivesInvalidKey(): void
    {
        $this->http->json(422, ['errors' => ['errorMessage' => 'API key expired']]);

        try {
            $this->provider()->token();
            self::fail('expected NpApiException');
        } catch (NpApiException $e) {
            self::assertSame(NpErrorKind::InvalidKey, $e->kind);
            self::assertStringNotContainsString(F::KEY, $e->getMessage());
        }
    }

    public function testMissingKeyGivesInvalidKeyWithoutRequests(): void
    {
        $this->key = null;

        try {
            $this->provider()->token();
            self::fail('expected NpApiException');
        } catch (NpApiException $e) {
            self::assertSame(NpErrorKind::InvalidKey, $e->kind);
        }
        self::assertCount(0, $this->http->requests());
    }

    public function testAnswerWithoutTokenIsMalformed(): void
    {
        $this->http->json(200, ['token' => 'x']);

        try {
            $this->provider()->token();
            self::fail('expected NpApiException');
        } catch (NpApiException $e) {
            self::assertSame(NpErrorKind::Malformed, $e->kind);
        }
    }

    public function testTransportErrorTextIsMasked(): void
    {
        $this->http->push(new TransportException('Failed to connect: ' . JwtProvider::AUTHORIZATION_URL . '?apiKey=' . F::KEY));

        try {
            $this->provider()->token();
            self::fail('expected NpApiException');
        } catch (NpApiException $e) {
            self::assertSame(NpErrorKind::Unavailable, $e->kind);
            self::assertStringNotContainsString(F::KEY, $e->getMessage());
        }
    }
}
