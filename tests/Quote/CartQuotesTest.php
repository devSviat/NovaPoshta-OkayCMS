<?php

namespace Modules\Sviat\NovaPoshta\Quote;

require_once __DIR__ . '/../Support/ApiFixtures.php';
require_once __DIR__ . '/../Support/FakeTransport.php';
require_once __DIR__ . '/../Support/InMemoryCityRefStore.php';
require_once __DIR__ . '/../Support/InMemoryMutex.php';
require_once __DIR__ . '/../Support/InMemoryState.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Modules\Sviat\NovaPoshta\Support\FakeTransport;
use Modules\Sviat\NovaPoshta\Support\InMemoryCityRefStore;
use Modules\Sviat\NovaPoshta\Support\InMemoryMutex;
use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Core\Money;
use Okay\Core\Settings;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;
use Okay\Modules\Sviat\NovaPoshta\Quote\AddressCityResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\CartQuotes;
use Okay\Modules\Sviat\NovaPoshta\Quote\CityRefResolver;
use Okay\Modules\Sviat\NovaPoshta\Quote\ClientAddress;
use Okay\Modules\Sviat\NovaPoshta\Quote\QuoteBudget;
use Okay\Modules\Sviat\NovaPoshta\Quote\QuoteService;
use PHPUnit\Framework\TestCase;

/** Кожен новий розрахунок у кошику — запит до НП на ключі магазину: cookie сесії не повинна виводити клієнта з-під лімітів. */
class CartQuotesTest extends TestCase
{
    private const int NOW = 1_900_000_000;

    private FakeTransport $http;
    private InMemoryState $state;
    private ?string $ip = '203.0.113.7';
    private CartQuotes $quotes;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
        $this->http = new FakeTransport();
        $this->state = new InMemoryState();

        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnCallback(static fn(string $name): mixed => $name === ShippingSettings::SENDER ? 2200878 : null);
        $divisions = $this->createStub(DivisionEntity::class);
        $divisions->method('get')->willReturnCallback(static fn(int $id): object => (object)['id' => $id, 'settlement_id' => 118064, 'status' => 'Working', 'prohibited_issuance' => 0]);
        $currencies = $this->createStub(CurrenciesEntity::class);
        $currencies->method('findOne')->willReturn((object)['id' => 1]);
        $deliveries = $this->createStub(DeliveriesEntity::class);
        $deliveries->method('getDeliveryPayments')->willReturn([]);
        $payments = $this->createStub(PaymentsEntity::class);
        $payments->method('noLimit')->willReturnSelf();
        $payments->method('find')->willReturn([]);
        $entities = $this->createStub(EntityFactory::class);
        $entities->method('get')->willReturnCallback(static fn(string $class): object => match ($class) {
            DivisionEntity::class => $divisions,
            CurrenciesEntity::class => $currencies,
            DeliveriesEntity::class => $deliveries,
            PaymentsEntity::class => $payments,
        });
        $money = $this->createStub(Money::class);
        $money->method('convert')->willReturnCallback(static fn(float $amount): string => (string)$amount);
        $module = $this->createStub(Module::class);
        $module->method('getModuleIdByNamespace')->willReturn(5);
        $client = $this->createStub(ClientAddress::class);
        $client->method('address')->willReturnCallback(fn(): ?string => $this->ip);

        $key = F::KEY;
        $apiKey = F::apiKey($this, $key);
        $old = new OldApiClient($this->http, $apiKey, static fn() => null);
        $new = new NewApiClient($this->http, new JwtProvider($this->http, new InMemoryState(), $apiKey, static fn(): int => self::NOW), static fn() => null);
        $cityRefs = new CityRefResolver($old, new InMemoryCityRefStore(), static fn(): int => self::NOW);
        $this->http->json(200, ['jwt' => F::jwt(self::NOW + 3600)]);

        $this->quotes = new CartQuotes(
            new QuoteService($new, $old, $cityRefs, static fn(): int => self::NOW),
            new ShippingSettings($settings, $entities),
            $entities,
            new CodPayments($entities),
            $module,
            $money,
            $this->state,
            new AddressCityResolver($old, new InMemoryCityRefStore(), $cityRefs),
            new InMemoryMutex(),
            $client,
        );
    }

    private static function delivery(): object
    {
        return (object)['id' => 3, 'module_id' => 5, 'free_from' => 1_000_000, 'hide_front_delivery_price' => 0, 'settings' => serialize(['service_type' => 'WarehouseWarehouse'])];
    }

    private static function cart(float $total = 500.0): object
    {
        return (object)['total_price' => $total, 'purchases' => [(object)['amount' => 1, 'variant' => (object)['id' => 1, 'weight' => 1.0, 'volume' => 0.001]]]];
    }

    private function answers(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->http->json(200, ['services' => [['cost' => 80]]]);
        }
    }

    /** Новий PHPSESSID на кожен запит: так виглядає клієнт, що ганяє розрахунки на ключі магазину. */
    private function freshFakeSession(): void
    {
        $_SESSION = [];
        $_COOKIE[session_name()] = bin2hex(random_bytes(16));
    }

    public function testOneIpWithFreshFakeSessionsHitsItsShareAndOthersStillGetQuotes(): void
    {
        $this->answers(QuoteBudget::VISITOR_LIMIT + 1);
        for ($i = 0; $i < QuoteBudget::VISITOR_LIMIT; $i++) {
            $this->freshFakeSession();
            self::assertSame(80.0, $this->quotes->quote(self::delivery(), 7162, 0, self::cart())->price, "request {$i}");
        }

        $this->freshFakeSession();
        self::assertSame('busy', $this->quotes->quote(self::delivery(), 7162, 0, self::cart())->problem, 'the IP share is spent whatever cookie comes with the request');

        $this->ip = '203.0.113.8';
        $this->freshFakeSession();
        self::assertSame(80.0, $this->quotes->quote(self::delivery(), 7162, 0, self::cart())->price, 'another customer still gets a price');
    }

    public function testIpv6ClientRotatingAddressesInsideItsSlash64ShareOneShare(): void
    {
        $this->answers(QuoteBudget::VISITOR_LIMIT + 1);
        for ($i = 0; $i < QuoteBudget::VISITOR_LIMIT; $i++) {
            $this->ip = '2001:db8:aa:1::' . dechex($i + 1);
            $this->freshFakeSession();
            self::assertSame(80.0, $this->quotes->quote(self::delivery(), 7162, 0, self::cart())->price, "request {$i}");
        }

        $this->ip = '2001:db8:aa:1:ffff::beef';
        $this->freshFakeSession();
        self::assertSame('busy', $this->quotes->quote(self::delivery(), 7162, 0, self::cart())->problem);
    }

    public function testSingleSessionIsThrottledAfterTwenty(): void
    {
        $this->answers(21);
        $_COOKIE[session_name()] = 'one-real-session';
        for ($i = 0; $i < 20; $i++) {
            self::assertSame(80.0, $this->quotes->quote(self::delivery(), 7162, 0, self::cart(500.0 + $i))->price, "request {$i}");
        }

        self::assertSame('busy', $this->quotes->quote(self::delivery(), 7162, 0, self::cart(900.0))->problem);
    }

    public function testOrderQuotesAreNeverChargedToTheBudget(): void
    {
        $this->answers(QuoteBudget::VISITOR_LIMIT + 5);
        for ($i = 0; $i < QuoteBudget::VISITOR_LIMIT + 5; $i++) {
            $this->freshFakeSession();
            self::assertSame(80.0, $this->quotes->quote(self::delivery(), 7162, 0, self::cart(), forOrder: true)->price);
        }

        self::assertNull($this->state->get('quote_budget'));
    }

    public function testStreetLookupsFromOneIpStopAtItsShareWhateverTheCookie(): void
    {
        for ($i = 0; $i < CartQuotes::STREET_PER_VISITOR; $i++) {
            $this->freshFakeSession();
            self::assertTrue($this->quotes->allowStreetLookup(), "lookup {$i}");
        }

        $this->freshFakeSession();
        self::assertFalse($this->quotes->allowStreetLookup());
        $this->ip = '203.0.113.8';
        self::assertTrue($this->quotes->allowStreetLookup(), 'another customer still gets streets');
    }
}
