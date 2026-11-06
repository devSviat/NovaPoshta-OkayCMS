<?php

namespace Modules\Sviat\NovaPoshta\KeyCheck;

require_once __DIR__ . '/../Support/FakeTransport.php';
require_once __DIR__ . '/../Support/InMemoryState.php';
require_once __DIR__ . '/../Support/ApiFixtures.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Modules\Sviat\NovaPoshta\Support\FakeTransport;
use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyAccess;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyChecker;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyCheckResult;
use PHPUnit\Framework\TestCase;

class KeyCheckerTest extends TestCase
{
    private const int NOW = 1_900_000_000;

    private FakeTransport $http;
    private InMemoryState $state;
    private ?string $savedKey = null;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->state = new InMemoryState();
    }

    private function checker(): KeyChecker
    {
        $apiKey = F::apiKey($this, $this->savedKey);
        $now = static fn(): int => self::NOW;
        $noSleep = static function (): void {
        };

        return new KeyChecker(
            $this->http,
            new OldApiClient($this->http, $apiKey, $noSleep),
            new JwtProvider($this->http, $this->state, $apiKey, $now),
            $this->state,
            $now,
        );
    }

    private function oldOk(): array
    {
        return ['success' => true, 'data' => [['Ref' => 'sender']], 'errors' => []];
    }

    public function testBadFormatMakesNoRequests(): void
    {
        $result = $this->checker()->check('not a key');

        self::assertSame(KeyAccess::BadFormat, $result->newApi);
        self::assertSame(KeyAccess::BadFormat, $result->oldApi);
        self::assertFalse($result->isUsable());
        self::assertCount(0, $this->http->requests());
    }

    public function testBothOk(): void
    {
        $jwt = F::jwt(self::NOW + 3600);
        $this->http->json(200, ['jwt' => $jwt])->json(200, $this->oldOk());

        $result = $this->checker()->check(' "' . F::KEY . '" ');

        self::assertSame(KeyAccess::Ok, $result->newApi);
        self::assertSame(KeyAccess::Ok, $result->oldApi);
        self::assertTrue($result->isUsable());
        self::assertSame(self::NOW, $result->checkedAt->getTimestamp());
        self::assertStringEndsWith('apiKey=' . F::KEY, $this->http->requests()[0]->url);
        self::assertSame(F::KEY, json_decode($this->http->requests()[1]->body, true)['apiKey']);
    }

    public function testIssuedTokenIsKeptForTheCheckedKey(): void
    {
        $jwt = F::jwt(self::NOW + 3600);
        $this->http->json(200, ['jwt' => $jwt])->json(200, $this->oldOk());
        $this->checker()->check(F::KEY);

        $this->savedKey = F::KEY;
        $provider = new JwtProvider($this->http, $this->state, F::apiKey($this, $this->savedKey), static fn(): int => self::NOW);
        self::assertSame($jwt, $provider->token());
        self::assertCount(2, $this->http->requests(), 'no second authorization request');
    }

    public function testExpiredKey(): void
    {
        $this->http
            ->json(422, ['errors' => ['errorMessage' => 'API key expired']])
            ->json(200, ['success' => false, 'data' => [], 'errors' => ['API key expired'], 'errorCodes' => ['20000201204']]);

        $result = $this->checker()->check(F::KEY);

        self::assertSame(KeyAccess::InvalidKey, $result->newApi);
        self::assertSame(KeyAccess::InvalidKey, $result->oldApi);
    }

    public function testNoNewApiAccess(): void
    {
        $this->http->json(401, ['errors' => ['errorMessage' => 'Unauthorized']])->json(200, $this->oldOk());

        $result = $this->checker()->check(F::KEY);

        self::assertSame(KeyAccess::Denied, $result->newApi);
        self::assertSame(KeyAccess::Ok, $result->oldApi);
        self::assertFalse($result->isUsable());
    }

    public function testUnreachableIsNotInvalid(): void
    {
        $this->http->push(
            new TransportException('timeout'),
            new TransportException('timeout'),
            new TransportException('timeout'),
        );

        $result = $this->checker()->check(F::KEY);

        self::assertSame(KeyAccess::Unreachable, $result->newApi);
        self::assertSame(KeyAccess::Unreachable, $result->oldApi);
    }

    public function testResultRoundTrip(): void
    {
        $result = new KeyCheckResult(KeyAccess::Denied, KeyAccess::Ok, new \DateTimeImmutable('@' . self::NOW));

        self::assertEquals($result, KeyCheckResult::fromJson($result->toJson()));
        self::assertNull(KeyCheckResult::fromJson('{"broken":'));
        self::assertNull(KeyCheckResult::fromJson('{"new":"nonsense","old":"ok","at":1}'));
    }

    public function testRememberedResultIsReadBack(): void
    {
        $checker = $this->checker();
        self::assertNull($checker->lastResult());

        $result = new KeyCheckResult(KeyAccess::Ok, KeyAccess::Unreachable, new \DateTimeImmutable('@' . self::NOW));
        $checker->remember($result);

        self::assertEquals($result, $this->checker()->lastResult());
        self::assertStringNotContainsString(F::KEY, implode('', $this->state->values));
    }
}
