<?php

namespace Modules\Sviat\NovaPoshta\Cart;

require_once __DIR__ . '/../Support/FakeTransport.php';
require_once __DIR__ . '/../Support/ApiFixtures.php';
require_once __DIR__ . '/../Support/InMemoryCityRefStore.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Modules\Sviat\NovaPoshta\Support\FakeTransport;
use Modules\Sviat\NovaPoshta\Support\InMemoryCityRefStore;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Cart\StreetSearch;
use Okay\Modules\Sviat\NovaPoshta\Quote\AddressCityResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\CityRefResolver;
use PHPUnit\Framework\TestCase;

class StreetSearchTest extends TestCase
{
    private const int NOW = 1_900_000_000;
    private const string KYIV = '8d5a980d-391c-11dd-90d9-001a92567626';

    private FakeTransport $http;
    private InMemoryCityRefStore $store;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->store = new InMemoryCityRefStore();
        $this->store->remember(118064, self::KYIV, self::NOW);
    }

    private function find(string $query, int $settlementId = 118064): array
    {
        $key = F::KEY;
        $old = new OldApiClient($this->http, F::apiKey($this, $key), static fn() => null);
        $now = static fn(): int => self::NOW;
        $search = new StreetSearch($old, new AddressCityResolver($old, $this->store, new CityRefResolver($old, $this->store, $now), $now));

        return $search->find((object)['id' => $settlementId, 'name_uk' => 'місто Київ', 'district_uk' => null, 'area_uk' => 'Київська область'], $query);
    }

    public function testStreetsFromNovaPoshta(): void
    {
        $this->http->json(200, ['success' => true, 'data' => [
            ['Ref' => '0f0d85b0-0000-4000-8000-000000000001', 'Description' => 'Хрещатик', 'StreetsType' => 'вул.'],
            ['Ref' => 'not-a-ref', 'Description' => 'Зла', 'StreetsType' => 'вул.'],
        ], 'errors' => []]);

        self::assertSame(['streets' => [['ref' => '0f0d85b0-0000-4000-8000-000000000001', 'name' => 'вул. Хрещатик']], 'problem' => null], $this->find(' Хрещ '));
        $p = json_decode((string)$this->http->requests()[0]->body, true)['methodProperties'];
        self::assertSame([self::KYIV, 'Хрещ', '10'], [$p['CityRef'], $p['FindByString'], $p['Limit']]);
        self::assertSame(5, $this->http->requests()[0]->timeoutSeconds);
    }

    public function testTooShortOrTooLongAsksNothing(): void
    {
        self::assertSame(['streets' => [], 'problem' => null], $this->find('Х'));
        self::assertSame(['streets' => [], 'problem' => null], $this->find(str_repeat('х', 51)));
        self::assertSame([], $this->http->requests());
    }

    public function testUnreachableGivesEmptyList(): void
    {
        $this->http->push(new TransportException('timeout'));

        self::assertSame(['streets' => [], 'problem' => 'unreachable'], $this->find('Хрещ'));
    }

    public function testUnknownCityGivesEmptyList(): void
    {
        $this->store->remember(5, null, self::NOW);

        self::assertSame(['streets' => [], 'problem' => 'no_city_ref'], $this->find('Хрещ', 5));
    }
}
