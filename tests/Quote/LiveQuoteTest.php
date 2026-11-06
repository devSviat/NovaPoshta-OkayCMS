<?php

namespace Modules\Sviat\NovaPoshta\Quote;

require_once __DIR__ . '/../Support/ApiFixtures.php';
require_once __DIR__ . '/../Support/InMemoryState.php';
require_once __DIR__ . '/../Support/InMemoryCityRefStore.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Modules\Sviat\NovaPoshta\Support\InMemoryCityRefStore;
use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\CurlTransport;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Quote\CityRefResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;
use Okay\Modules\Sviat\NovaPoshta\Quote\Parcel;
use Okay\Modules\Sviat\NovaPoshta\Quote\QuoteRequest;
use Okay\Modules\Sviat\NovaPoshta\Quote\QuoteService;
use PHPUnit\Framework\TestCase;

/** Calculations only, nothing is created at Nova Poshta; runs only with NP_TEST_API_KEY (a test key). */
class LiveQuoteTest extends TestCase
{
    private QuoteService $service;

    protected function setUp(): void
    {
        $key = getenv('NP_TEST_API_KEY');
        if (!is_string($key) || $key === '') {
            self::markTestSkipped('NP_TEST_API_KEY is not set');
        }
        $http = new CurlTransport();
        $apiKey = F::apiKey($this, $key);
        $old = new OldApiClient($http, $apiKey);
        $refs = new InMemoryCityRefStore();
        $refs->numbers = [121300 => ['74/1'], 118064 => ['11/1']];
        $this->service = new QuoteService(new NewApiClient($http, new JwtProvider($http, new InMemoryState(), $apiKey)), $old, new CityRefResolver($old, $refs));
    }

    private function quote(bool $cod, bool $term = false): \Okay\Modules\Sviat\NovaPoshta\Quote\DeliveryQuote
    {
        return $this->service->quote(new QuoteRequest(
            'WarehouseWarehouse',
            (object)['id' => 2200878, 'settlement_id' => 121300],
            (object)['id' => 7162, 'settlement_id' => 118064],
            new Parcel(2.0, 0.001, 1000.0),
            $cod,
            CodKind::Money,
            true,
            $term,
        ));
    }

    public function testBothApisAgreeOnTheBasePrice(): void
    {
        $new = $this->quote(false, true);
        $old = $this->quote(true);

        self::assertSame('new', $new->source);
        self::assertSame('old', $old->source, (string)$old->problem);
        self::assertGreaterThan(0, $new->price);
        self::assertGreaterThan($new->price, $old->price, 'COD commission is on top');
        self::assertNotNull($new->term, (string)$new->problem);
        self::assertGreaterThan(new \DateTimeImmutable('today'), $new->term);
    }
}
