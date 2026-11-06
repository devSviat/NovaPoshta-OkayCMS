<?php

namespace Modules\Sviat\NovaPoshta\Quote;

require_once __DIR__ . '/../Support/FakeTransport.php';
require_once __DIR__ . '/../Support/InMemoryState.php';
require_once __DIR__ . '/../Support/ApiFixtures.php';
require_once __DIR__ . '/../Support/InMemoryCityRefStore.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Modules\Sviat\NovaPoshta\Support\FakeTransport;
use Modules\Sviat\NovaPoshta\Support\InMemoryCityRefStore;
use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Quote\CityRefResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;
use Okay\Modules\Sviat\NovaPoshta\Quote\Parcel;
use Okay\Modules\Sviat\NovaPoshta\Quote\QuoteRequest;
use Okay\Modules\Sviat\NovaPoshta\Quote\QuoteService;
use PHPUnit\Framework\TestCase;

class QuoteServiceTest extends TestCase
{
    private const int NOW = 1_900_000_000;
    private const string LVIV = 'db5c88f5-391c-11dd-90d9-001a92567626';
    private const string KYIV = '8d5a980d-391c-11dd-90d9-001a92567626';

    private FakeTransport $http;
    private InMemoryCityRefStore $refs;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->refs = new InMemoryCityRefStore();
        $this->refs->remember(121300, self::LVIV, self::NOW);
        $this->refs->remember(118064, self::KYIV, self::NOW);
    }

    private function service(): QuoteService
    {
        $key = F::KEY;
        $apiKey = F::apiKey($this, $key);
        $old = new OldApiClient($this->http, $apiKey, static fn() => null);
        $new = new NewApiClient($this->http, new JwtProvider($this->http, new InMemoryState(), $apiKey, static fn(): int => self::NOW), static fn() => null);

        return new QuoteService($new, $old, new CityRefResolver($old, $this->refs, static fn(): int => self::NOW), static fn(): int => self::NOW);
    }

    private static function request(string $serviceType = 'WarehouseWarehouse', bool $cod = false, CodKind $kind = CodKind::Money, bool $price = true, bool $term = false): QuoteRequest
    {
        return new QuoteRequest(
            serviceType: $serviceType,
            sender: (object)['id' => 2200878, 'settlement_id' => 121300],
            recipient: (object)['id' => 7162, 'settlement_id' => 118064],
            parcel: new Parcel(2.0, 0.001, 1000.0),
            cod: $cod,
            codKind: $kind,
            wantPrice: $price,
            wantTerm: $term,
        );
    }

    private function jwt(): self
    {
        $this->http->json(200, ['jwt' => F::jwt(self::NOW + 3600)]);

        return $this;
    }

    private function body(int $i): array
    {
        return json_decode((string)$this->http->requests()[$i]->body, true);
    }

    public function testBranchWithoutCodIsPricedByTheNewApi(): void
    {
        $this->jwt()->http->json(200, ['services' => [['cost' => 95], ['cost' => 10.5]]]);

        $quote = $this->service()->quote(self::request());

        self::assertSame(105.5, $quote->price);
        self::assertSame('new', $quote->source);
        self::assertNull($quote->term);
        $body = $this->body(1);
        self::assertSame('Recipient', $body['payerType']);
        self::assertSame(['countryCode' => 'UA', 'divisionId' => 2200878], $body['sender']);
        self::assertSame(['countryCode' => 'UA', 'divisionId' => 7162], $body['recipient']);
        self::assertSame(['cargoCategory' => 'parcel', 'insuranceCost' => 1000, 'rowNumber' => 1, 'width' => 100, 'length' => 100, 'height' => 100, 'actualWeight' => 2000], $body['parcels'][0]);
        self::assertSame(5, $this->http->requests()[1]->timeoutSeconds);
    }

    public function testMoneyTransferCodIsPricedByTheOldApiWithItsCommission(): void
    {
        $this->http->json(200, ['success' => true, 'data' => [['Cost' => 95, 'CostRedelivery' => 40]], 'errors' => []]);

        $quote = $this->service()->quote(self::request(cod: true));

        self::assertSame(135.0, $quote->price);
        self::assertSame('old', $quote->source);
        $p = $this->body(0)['methodProperties'];
        self::assertSame([self::LVIV, self::KYIV, 'WarehouseWarehouse', '2', '0.001', '1000'], [$p['CitySender'], $p['CityRecipient'], $p['ServiceType'], $p['Weight'], $p['VolumeGeneral'], $p['Cost']]);
        self::assertSame(['CargoType' => 'Money', 'Amount' => '1000'], $p['RedeliveryCalculate']);
    }

    public function testPaymentControlKeepsTheCustomersPrice(): void
    {
        $this->jwt()->http->json(200, ['services' => [['cost' => 95]]]);

        self::assertSame(95.0, $this->service()->quote(self::request(cod: true, kind: CodKind::Control))->price);
    }

    public function testCourierPickupIsPricedByTheOldApi(): void
    {
        $this->http->json(200, ['success' => true, 'data' => [['Cost' => 130]], 'errors' => []]);

        $quote = $this->service()->quote(self::request(serviceType: 'DoorsWarehouse'));

        self::assertSame(130.0, $quote->price);
        self::assertSame('DoorsWarehouse', $this->body(0)['methodProperties']['ServiceType']);
        self::assertArrayNotHasKey('RedeliveryCalculate', $this->body(0)['methodProperties']);
    }

    public function testTermFromTheOldApi(): void
    {
        $this->jwt()->http->json(200, ['services' => [['cost' => 95]]])
            ->json(200, ['success' => true, 'data' => [['DeliveryDate' => ['date' => '2030-03-18 18:00:00.000000', 'timezone_type' => 3, 'timezone' => 'Europe/Kiev']]], 'errors' => []]);

        $quote = $this->service()->quote(self::request(term: true));

        self::assertSame('2030-03-18', $quote->term?->format('Y-m-d'));
        $p = $this->body(2)['methodProperties'];
        self::assertSame([self::LVIV, self::KYIV, 'WarehouseWarehouse', date('d.m.Y', self::NOW)], [$p['CitySender'], $p['CityRecipient'], $p['ServiceType'], $p['DateTime']]);
    }

    public function testHiddenPriceAsksOnlyTerm(): void
    {
        $this->http->json(200, ['success' => true, 'data' => [['DeliveryDate' => ['date' => '2030-03-18 18:00:00.000000']]], 'errors' => []]);

        $quote = $this->service()->quote(self::request(price: false, term: true));

        self::assertNull($quote->price);
        self::assertSame('none', $quote->source);
        self::assertCount(1, $this->http->requests());
    }

    public function testNothingWantedAsksNothing(): void
    {
        $quote = $this->service()->quote(self::request(price: false, term: false));

        self::assertSame([], $this->http->requests());
        self::assertNull($quote->problem);
    }

    public function testUnreachableGivesFallback(): void
    {
        $this->jwt()->http->push(new TransportException('timeout'));

        $quote = $this->service()->quote(self::request());

        self::assertNull($quote->price);
        self::assertSame('fallback', $quote->source);
        self::assertSame('unreachable', $quote->problem);
    }

    public function testRejectedGivesFallback(): void
    {
        $this->http->json(200, ['success' => false, 'data' => [], 'errors' => ['Weight is invalid'], 'errorCodes' => []]);

        $quote = $this->service()->quote(self::request(cod: true));

        self::assertSame(['fallback', 'rejected'], [$quote->source, $quote->problem]);
    }

    public function testNoCityRefGivesProblem(): void
    {
        $refs = $this->refs;
        $refs->rows = [];
        $refs->remember(118064, null, self::NOW);
        $refs->remember(121300, self::LVIV, self::NOW);

        $quote = $this->service()->quote(self::request(cod: true));

        self::assertSame(['fallback', 'no_city_ref'], [$quote->source, $quote->problem]);
        self::assertSame([], $this->http->requests());
    }

    public function testEmptyNewApiAnswerIsNotAFreeDelivery(): void
    {
        $this->jwt()->http->json(200, ['services' => []]);

        self::assertSame('fallback', $this->service()->quote(self::request())->source);
    }
}
