<?php

namespace Modules\Sviat\NovaPoshta\KeyCheck;

require_once __DIR__ . '/../Support/FakeTransport.php';
require_once __DIR__ . '/../Support/InMemoryState.php';
require_once __DIR__ . '/../Support/ApiFixtures.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Modules\Sviat\NovaPoshta\Support\FakeTransport;
use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyAccess;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyChecker;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyCheckResult;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyRejections;
use PHPUnit\Framework\TestCase;

class KeyRejectionsTest extends TestCase
{
    private const int CHECKED = 1_900_000_000;
    private const int NOW = 1_900_086_400;

    private InMemoryState $state;
    private ?string $savedKey = F::KEY;

    protected function setUp(): void
    {
        $this->state = new InMemoryState();
        $this->state->set(KeyChecker::STATE, new KeyCheckResult(KeyAccess::Ok, KeyAccess::Ok, new \DateTimeImmutable('@' . self::CHECKED))->toJson());
    }

    private function rejections(): KeyRejections
    {
        return new KeyRejections($this->state, F::apiKey($this, $this->savedKey), static fn(): int => self::NOW);
    }

    private function last(): ?KeyCheckResult
    {
        return KeyCheckResult::fromJson((string)$this->state->get(KeyChecker::STATE));
    }

    /** A key that stopped working shows in the key block, not only as failing waybills. */
    public function testRefusalDuringWorkMarksTheKey(): void
    {
        $this->rejections()->record('old', F::KEY);

        self::assertSame([KeyAccess::Ok, KeyAccess::InvalidKey, self::NOW], [$this->last()->newApi, $this->last()->oldApi, $this->last()->checkedAt->getTimestamp()]);
    }

    public function testRefusalOfAKeyNoLongerSavedIsIgnored(): void
    {
        $this->savedKey = 'b' . substr(F::KEY, 1);

        $this->rejections()->record('new', F::KEY);

        self::assertSame([KeyAccess::Ok, KeyAccess::Ok, self::CHECKED], [$this->last()->newApi, $this->last()->oldApi, $this->last()->checkedAt->getTimestamp()]);
    }

    public function testFirstRefusalTimeIsKept(): void
    {
        $this->rejections()->record('new', F::KEY);
        new KeyRejections($this->state, F::apiKey($this, $this->savedKey), static fn(): int => self::NOW + 900)->record('new', F::KEY);

        self::assertSame(self::NOW, $this->last()->checkedAt->getTimestamp());
    }

    public function testOldApiClientReportsTheSavedKeyOnly(): void
    {
        $http = new FakeTransport();
        $http->json(200, ['success' => false, 'data' => [], 'errors' => ['API key expired'], 'errorCodes' => ['20000201204']]);
        $http->json(200, ['success' => false, 'data' => [], 'errors' => ['API key expired'], 'errorCodes' => ['20000201204']]);
        $client = new OldApiClient($http, F::apiKey($this, $this->savedKey), static function (): void {
        }, $this->rejections());

        try {
            $client->withKey(F::KEY)->call('Counterparty', 'getCounterparties');
        } catch (NpApiException) {
        }
        self::assertSame(KeyAccess::Ok, $this->last()->oldApi, 'a key being checked is not the saved one at work');

        try {
            $client->call('Counterparty', 'getCounterparties');
        } catch (NpApiException) {
        }
        self::assertSame(KeyAccess::InvalidKey, $this->last()->oldApi);
    }

    public function testJwtProviderReportsARefusedKey(): void
    {
        $http = new FakeTransport();
        $http->json(422, ['errors' => ['errorMessage' => 'API key expired']]);
        $provider = new JwtProvider($http, $this->state, F::apiKey($this, $this->savedKey), static fn(): int => self::NOW, $this->rejections());

        try {
            $provider->token();
        } catch (NpApiException) {
        }

        self::assertSame(KeyAccess::InvalidKey, $this->last()->newApi);
    }
}
