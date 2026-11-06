<?php

namespace Modules\Sviat\NovaPoshta\Dictionaries;

require_once __DIR__ . '/../Support/FakeTransport.php';
require_once __DIR__ . '/../Support/InMemoryState.php';
require_once __DIR__ . '/../Support/ApiFixtures.php';
require_once __DIR__ . '/../Support/InMemoryDictionaryStore.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Modules\Sviat\NovaPoshta\Support\FakeTransport;
use Modules\Sviat\NovaPoshta\Support\InMemoryDictionaryStore;
use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpResponse;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\BoostUpdater;
use PHPUnit\Framework\TestCase;

class BoostUpdaterTest extends TestCase
{
    private const int EXP = 2_000_000_000;

    private FakeTransport $http;
    private InMemoryDictionaryStore $store;
    private ?string $key = F::KEY;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)]);
        $this->store = new InMemoryDictionaryStore();
        $this->store->tables['settlements'] = [
            1 => ['id' => 1, 'boost' => 9], 2 => ['id' => 2, 'boost' => 0], 3 => ['id' => 3, 'boost' => 4],
        ];
    }

    private function updater(): BoostUpdater
    {
        $jwt = new JwtProvider($this->http, new InMemoryState(), F::apiKey($this, $this->key), static fn(): int => self::EXP - 3600);

        return new BoostUpdater(new NewApiClient($this->http, $jwt, static function (): void {
        }), $this->store);
    }

    private function page(int $page, int $last, array $boosts): void
    {
        $items = [];
        foreach ($boosts as $id => $boost) {
            $items[] = ['id' => $id, 'name' => "n{$id}", 'boost' => $boost];
        }
        $this->http->json(200, ['current_page' => $page, 'last_page' => $last, 'items' => $items]);
    }

    public function testStopsAtTheFirstPageWithoutBoost(): void
    {
        $this->page(1, 276, [1 => 500, 2 => 100]);
        $this->page(2, 276, [3 => 1, 4 => 0]);
        $this->page(3, 276, [5 => 0, 6 => 0]);

        self::assertSame(3, $this->updater()->update());

        self::assertSame(500, $this->store->tables['settlements'][1]['boost']);
        self::assertSame(100, $this->store->tables['settlements'][2]['boost']);
        self::assertSame(1, $this->store->tables['settlements'][3]['boost']);
        self::assertCount(4, $this->http->requests(), 'authorization + 3 pages');
        self::assertStringContainsString('/v.1.0/settlements?countryCodes%5B0%5D=UA&limit=100&page=2', $this->http->requests()[2]->url);
    }

    public function testFirstPageWithoutBoostIsAnError(): void
    {
        $this->page(1, 276, [1 => 0, 2 => 0]);

        try {
            $this->updater()->update();
            self::fail('expected NpApiException');
        } catch (NpApiException) {
        }
        self::assertSame([9, 0, 4], array_column($this->store->tables['settlements'], 'boost'), 'boosts are kept');
    }

    public function testStopsAtTheLastPage(): void
    {
        $this->page(1, 1, [1 => 5]);

        self::assertSame(1, $this->updater()->update());
        self::assertCount(2, $this->http->requests());
    }

    public function testPageLimitStopsAnEndlessList(): void
    {
        for ($page = 1; $page <= BoostUpdater::MAX_PAGES + 5; $page++) {
            $this->page($page, 9999, [$page => 1]);
        }

        $this->updater()->update();

        self::assertCount(1 + BoostUpdater::MAX_PAGES, $this->http->requests());
    }

    public function testFailureMidwayChangesNothing(): void
    {
        $this->page(1, 276, [1 => 500]);
        $this->page(2, 276, [2 => 7]);
        $this->http->push(new HttpResponse(500, 'oops'), new HttpResponse(500, 'oops'));

        try {
            $this->updater()->update();
            self::fail('expected NpApiException');
        } catch (NpApiException) {
        }

        self::assertSame([9, 0, 4], array_column($this->store->tables['settlements'], 'boost'));
        self::assertNotContains('setBoost 2', $this->store->calls);
    }

    public function testWithoutKeyNothingIsRequested(): void
    {
        $this->key = null;
        $this->http = new FakeTransport();

        $this->expectException(NpApiException::class);
        try {
            $this->updater()->update();
        } finally {
            self::assertCount(0, $this->http->requests());
        }
    }
}
