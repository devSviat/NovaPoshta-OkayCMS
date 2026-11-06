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
use Okay\Modules\Sviat\NovaPoshta\Quote\AddressCityResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\CityRefResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\CityRefUnavailable;
use PHPUnit\Framework\TestCase;

class AddressCityResolverTest extends TestCase
{
    private const int NOW = 1_900_000_000;
    private const string MAKARIV = 'a88bc669-0000-4000-8000-000000000001';

    private FakeTransport $http;
    private InMemoryCityRefStore $store;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->store = new InMemoryCityRefStore();
    }

    private function resolver(): AddressCityResolver
    {
        $key = F::KEY;
        $old = new OldApiClient($this->http, F::apiKey($this, $key), static fn() => null);
        $now = static fn(): int => self::NOW;

        return new AddressCityResolver($old, $this->store, new CityRefResolver($old, $this->store, $now), $now);
    }

    private static function settlement(int $id, string $name, ?string $district, string $area): object
    {
        return (object)['id' => $id, 'name_uk' => $name, 'district_uk' => $district, 'area_uk' => $area];
    }

    private static function found(array ...$rows): array
    {
        $addresses = array_map(static fn(array $r): array => ['MainDescription' => $r[0], 'Area' => $r[1], 'Region' => $r[2], 'DeliveryCity' => $r[3], 'SettlementTypeCode' => 'с.'], $rows);

        return ['success' => true, 'data' => [['TotalCount' => count($addresses), 'Addresses' => $addresses]], 'errors' => []];
    }

    public function testDistrictTellsNamesakesApart(): void
    {
        $this->http->json(200, self::found(
            ["Мар'янівка", 'Київська', 'Поліський', 'f466406c-0000-4000-8000-000000000001'],
            ["Мар’янівка", 'Київська', 'Макарівський', self::MAKARIV],
            ["Мар'янівка", 'Київська', 'Білоцерківський', '77bf5e88-0000-4000-8000-000000000001'],
            ["Мар'янівка", 'Житомирська', 'Макарівський', 'aaaaaaaa-0000-4000-8000-000000000001'],
        ));

        self::assertSame(self::MAKARIV, $this->resolver()->forSettlement(self::settlement(515025, "село Мар'янівка (Колонщинська сільська рада)", 'Макарівський район', 'Київська область')));
        $body = json_decode((string)$this->http->requests()[0]->body, true);
        self::assertSame(['Address', 'searchSettlements', "Мар'янівка"], [$body['modelName'], $body['calledMethod'], $body['methodProperties']['CityName']]);
        self::assertSame(self::MAKARIV, $this->store->cached(515025)['ref']);
    }

    public function testNoAddressDeliveryIsNull(): void
    {
        $this->http->json(200, self::found(['Березенщина', 'Київська', 'Васильківський', '']));

        self::assertNull($this->resolver()->forSettlement(self::settlement(497705, 'село Березенщина', 'Васильківський район', 'Київська область')));
        self::assertSame(['ref' => null, 'checkedAt' => self::NOW], $this->store->cached(497705));
    }

    public function testTwoMatchesInOneDistrictAreNotGuessed(): void
    {
        $this->http->json(200, self::found(['Іванівка', 'Одеська', 'Березівський', 'bbbbbbbb-0000-4000-8000-000000000001'], ['Іванівка', 'Одеська', 'Березівський', 'cccccccc-0000-4000-8000-000000000001']));

        self::assertNull($this->resolver()->forSettlement(self::settlement(1, 'село Іванівка', 'Березівський район', 'Одеська область')));
    }

    public function testCityWithoutDistrict(): void
    {
        $this->http->json(200, self::found(['Київ', 'Київська', '', '8d5a980d-391c-11dd-90d9-001a92567626'], ['Київ', 'Миколаївська', 'Вознесенський', '06f8795a-4079-11de-b509-001d92f78698']));

        self::assertSame('8d5a980d-391c-11dd-90d9-001a92567626', $this->resolver()->forSettlement(self::settlement(118064, 'місто Київ', null, 'Київська область')));
    }

    public function testPointNumberBridgeComesFirst(): void
    {
        $this->store->numbers[118064] = ['11/1'];
        $this->http->json(200, ['success' => true, 'data' => [['WarehouseIndex' => '11/1', 'CityRef' => '8d5a980d-391c-11dd-90d9-001a92567626']], 'errors' => []]);

        self::assertSame('8d5a980d-391c-11dd-90d9-001a92567626', $this->resolver()->forSettlement(self::settlement(118064, 'місто Київ', null, 'Київська область')));
        self::assertCount(1, $this->http->requests());
    }

    public function testFreshCacheMakesNoRequest(): void
    {
        $this->store->remember(5, self::MAKARIV, self::NOW - 100);

        self::assertSame(self::MAKARIV, $this->resolver()->forSettlement(self::settlement(5, 'село X', null, 'Київська область')));
        self::assertSame([], $this->http->requests());
    }

    public function testOutageIsNotUnknown(): void
    {
        $this->http->push(new TransportException('timeout'));

        $this->expectException(CityRefUnavailable::class);
        $this->resolver()->forSettlement(self::settlement(5, 'село X', null, 'Київська область'));
    }

    private static function page(int $total, array ...$rows): array
    {
        $answer = self::found(...$rows);
        $answer['data'][0]['TotalCount'] = $total;

        return $answer;
    }

    public function testNamesakeOnTheNextPageIsNotMissed(): void
    {
        $this->http->json(200, self::page(3, ['Іванівка', 'Одеська', 'Березівський', 'bbbbbbbb-0000-4000-8000-000000000001'], ['Іванівка', 'Львівська', 'Стрийський', 'dddddddd-0000-4000-8000-000000000001']));
        $this->http->json(200, self::page(3, ['Іванівка', 'Одеська', 'Березівський', 'cccccccc-0000-4000-8000-000000000001']));

        self::assertNull($this->resolver()->forSettlement(self::settlement(1, 'село Іванівка', 'Березівський район', 'Одеська область')));
        self::assertSame('2', json_decode((string)$this->http->requests()[1]->body, true)['methodProperties']['Page']);
    }

    public function testUniqueAcrossAllPagesIsFound(): void
    {
        $this->http->json(200, self::page(2, ['Іванівка', 'Одеська', 'Березівський', 'bbbbbbbb-0000-4000-8000-000000000001']));
        $this->http->json(200, self::page(2, ['Іванівка', 'Львівська', 'Стрийський', 'dddddddd-0000-4000-8000-000000000001']));

        self::assertSame('bbbbbbbb-0000-4000-8000-000000000001', $this->resolver()->forSettlement(self::settlement(1, 'село Іванівка', 'Березівський район', 'Одеська область')));
    }

    public function testTooManyNamesakesToReadAreNotGuessed(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->http->json(200, self::page(1000, ['Іванівка', $i === 0 ? 'Одеська' : 'Львівська', $i === 0 ? 'Березівський' : 'Стрийський', 'bbbbbbbb-0000-4000-8000-00000000000' . $i]));
        }

        self::assertNull($this->resolver()->forSettlement(self::settlement(1, 'село Іванівка', 'Березівський район', 'Одеська область')));
        self::assertCount(4, $this->http->requests());
    }
}
