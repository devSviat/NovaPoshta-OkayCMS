<?php

namespace Modules\Sviat\NovaPoshta\Quote;

require_once __DIR__ . '/../Support/FakeTransport.php';
require_once __DIR__ . '/../Support/ApiFixtures.php';
require_once __DIR__ . '/../Support/InMemoryCityRefStore.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Modules\Sviat\NovaPoshta\Support\FakeTransport;
use Modules\Sviat\NovaPoshta\Support\InMemoryCityRefStore;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Quote\CityRefResolver;
use PHPUnit\Framework\TestCase;

class CityRefResolverTest extends TestCase
{
    private const int NOW = 1_900_000_000;
    private const string KYIV = '8d5a980d-391c-11dd-90d9-001a92567626';

    private FakeTransport $http;
    private InMemoryCityRefStore $store;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->store = new InMemoryCityRefStore();
        $this->store->numbers[118064] = ['11/1', '11/2', '11/3', '11/4'];
    }

    private function resolver(): CityRefResolver
    {
        $key = F::KEY;

        return new CityRefResolver(new OldApiClient($this->http, F::apiKey($this, $key), static fn() => null), $this->store, static fn(): int => self::NOW);
    }

    private function warehouse(string $index, string $cityRef): array
    {
        return ['success' => true, 'data' => [['WarehouseIndex' => $index, 'CityRef' => $cityRef]], 'errors' => []];
    }

    public function testFreshCacheMakesNoRequest(): void
    {
        $this->store->remember(118064, self::KYIV, self::NOW - 29 * 86400);

        self::assertSame(self::KYIV, $this->resolver()->forSettlement(118064));
        self::assertSame([], $this->http->requests());
    }

    public function testStaleCacheIsRefreshed(): void
    {
        $this->store->remember(118064, 'aaaaaaaa-0000-0000-0000-000000000000', self::NOW - 31 * 86400);
        $this->http->json(200, $this->warehouse('11/1', self::KYIV));

        self::assertSame(self::KYIV, $this->resolver()->forSettlement(118064));
        self::assertSame(['ref' => self::KYIV, 'checkedAt' => self::NOW], $this->store->cached(118064));
        $body = json_decode($this->http->requests()[0]->body, true);
        self::assertSame(['Address', 'getWarehouses', '11/1'], [$body['modelName'], $body['calledMethod'], $body['methodProperties']['WarehouseIndex']]);
    }

    public function testNextNumberIsTriedWhenTheFirstGivesNothing(): void
    {
        $this->http->json(200, ['success' => true, 'data' => [], 'errors' => []])
            ->json(200, $this->warehouse('99/9', self::KYIV))
            ->json(200, $this->warehouse('11/3', self::KYIV));

        self::assertSame(self::KYIV, $this->resolver()->forSettlement(118064), 'an answer for another number is not trusted');
        self::assertCount(3, $this->http->requests(), 'at most 3 numbers are tried');
    }

    public function testNoBridgeIsRemembered(): void
    {
        $this->store->numbers[5] = ['1/1'];
        $this->http->json(200, ['success' => true, 'data' => [], 'errors' => []]);

        self::assertNull($this->resolver()->forSettlement(5));
        self::assertSame(['ref' => null, 'checkedAt' => self::NOW], $this->store->cached(5));

        self::assertNull($this->resolver()->forSettlement(5), 'remembered for a day');
        self::assertCount(1, $this->http->requests());
    }

    public function testNoNumbersAtAllIsRememberedWithoutRequests(): void
    {
        self::assertNull($this->resolver()->forSettlement(77));
        self::assertSame([], $this->http->requests());
        self::assertNotNull($this->store->cached(77));
    }

    public function testNetworkFailureIsNotRemembered(): void
    {
        $this->http->push(new TransportException('timeout'));

        self::assertNull($this->resolver()->forSettlement(118064));
        self::assertNull($this->store->cached(118064));
    }

    public function testNetworkFailureKeepsAStaleRef(): void
    {
        $this->store->remember(118064, self::KYIV, self::NOW - 40 * 86400);
        $this->http->push(new TransportException('timeout'));

        self::assertSame(self::KYIV, $this->resolver()->forSettlement(118064));
    }

    public function testMalformedRefIsIgnored(): void
    {
        $this->store->numbers[5] = ['1/1'];
        $this->http->json(200, $this->warehouse('1/1', '<script>'));

        self::assertNull($this->resolver()->forSettlement(5));
    }

    public function testRateLimitIsWaitedOut(): void
    {
        $this->http->json(200, ['success' => false, 'data' => [], 'errors' => ['To many requests'], 'errorCodes' => []])
            ->json(200, $this->warehouse('11/1', self::KYIV));

        self::assertSame(self::KYIV, $this->resolver()->forSettlement(118064));
    }
}
