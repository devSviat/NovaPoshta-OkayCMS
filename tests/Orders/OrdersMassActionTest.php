<?php

namespace Modules\Sviat\NovaPoshta\Orders;

require_once __DIR__ . '/../Support/FakeTransport.php';
require_once __DIR__ . '/../Support/ApiFixtures.php';
require_once __DIR__ . '/../Support/InMemoryState.php';
require_once __DIR__ . '/../Support/InMemoryWaybillRows.php';
require_once __DIR__ . '/../Support/WaybillFixtures.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Modules\Sviat\NovaPoshta\Support\FakeTransport;
use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Modules\Sviat\NovaPoshta\Support\InMemoryWaybillRows;
use Modules\Sviat\NovaPoshta\Support\WaybillFixtures as W;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrdersMassAction;
use Okay\Modules\Sviat\NovaPoshta\Waybills\MassOrders;
use Okay\Modules\Sviat\NovaPoshta\Waybills\MassReport;
use Okay\Modules\Sviat\NovaPoshta\Waybills\MassWaybills;
use Okay\Modules\Sviat\NovaPoshta\Waybills\RecipientCities;
use Okay\Modules\Sviat\NovaPoshta\Waybills\SenderParty;
use Okay\Modules\Sviat\NovaPoshta\Waybills\SenderSource;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillDraft;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillService;
use PHPUnit\Framework\TestCase;

class OrdersMassActionTest extends TestCase
{
    private FakeTransport $http;
    private InMemoryWaybillRows $rows;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->rows = new InMemoryWaybillRows();
    }

    private function action(): OrdersMassAction
    {
        $key = F::KEY;
        $apiKey = F::apiKey($this, $key);
        $noSleep = static function (): void {
        };
        $sender = new class implements SenderSource {
            public function party(): SenderParty
            {
                return W::sender();
            }
        };
        $cities = new class implements RecipientCities {
            public function cityRef(int $settlementId): ?string
            {
                return null;
            }
        };
        $orders = new class implements MassOrders {
            public function isOurs(int $orderId): bool
            {
                return $orderId !== 990062;
            }

            public function draft(int $orderId): WaybillDraft
            {
                return W::point(['orderId' => $orderId, 'cod' => true, 'codAmount' => 500.0]);
            }
        };
        $jwt = new JwtProvider($this->http, new InMemoryState(), $apiKey, static fn(): int => 1_900_000_000);
        $service = new WaybillService(new OldApiClient($this->http, $apiKey, $noSleep), new NewApiClient($this->http, $jwt, $noSleep), $this->rows, $sender, $cities, static fn(): string => '07.11.2026', fn(): int => $this->rows->now);

        return new OrdersMassAction(new MassWaybills($service, $this->rows, $orders));
    }

    private static function post(array $override = []): array
    {
        return $override + ['action' => OrdersMassAction::ACTION, 'check' => ['990061', '990062']];
    }

    public function testWithoutSessionDoesNothing(): void
    {
        $session = [];

        self::assertFalse($this->action()->handle(self::post(), false, $session));
        self::assertSame([], $session);
        self::assertSame([], $this->http->requests());
    }

    public function testOtherActionDoesNothing(): void
    {
        $session = [];

        self::assertFalse($this->action()->handle(self::post(['action' => 'delete']), true, $session));
        self::assertSame([], $session);
    }

    public function testNoCheckDoesNothing(): void
    {
        $session = [];

        self::assertFalse($this->action()->handle(self::post(['check' => '990061']), true, $session));
        self::assertFalse($this->action()->handle(['action' => OrdersMassAction::ACTION], true, $session));
        self::assertSame([], $session);
    }

    public function testReportIsStoredAndTakenOnce(): void
    {
        $this->http->json(200, W::fixture('old_save_ok'));
        $this->http->json(200, ['success' => true, 'data' => [W::fixture('status_cod')], 'errors' => []]);
        $session = ['other' => 1];

        self::assertTrue($this->action()->handle(self::post(), true, $session));
        $report = OrdersMassAction::take($session);

        self::assertInstanceOf(MassReport::class, $report);
        self::assertSame([990061, 990062], array_column($report->rows, 'orderId'));
        self::assertSame(['other' => 1], $session);
        self::assertNull(OrdersMassAction::take($session));
    }

    public function testViewTranslatesEveryRow(): void
    {
        $report = new MassReport([
            ['orderId' => 990061, 'kind' => 'created', 'key' => null, 'value' => '20450000000011'],
            ['orderId' => 990063, 'kind' => 'skipped', 'key' => 'mass_has_waybill', 'value' => '20450000000003'],
            ['orderId' => 990064, 'kind' => 'skipped', 'key' => 'mass_not_np', 'value' => null],
            ['orderId' => 990065, 'kind' => 'failed', 'key' => 'waybill_advice_phone', 'value' => 'RecipientsPhone invalid format'],
            ['orderId' => 990066, 'kind' => 'failed', 'key' => 'waybill_advice_generic', 'value' => 'Something unexpected'],
        ], 8);
        $texts = [
            'mass_title' => 'створено %1$s з %2$s', 'mass_created' => 'накладна %s', 'mass_skipped' => 'пропущено', 'mass_failed' => 'НП відмовила',
            'mass_has_waybill' => 'накладна вже є (%s)', 'mass_not_np' => 'доставка не Новою Поштою', 'waybill_advice_phone' => 'Виправте телефон.', 'waybill_advice_generic' => 'Перевірте дані.',
            'mass_untouched' => 'до %1$s; решту %2$s не чіпали',
        ];

        $view = OrdersMassAction::view($report, static fn(string $key): string => $texts[$key]);

        self::assertSame('створено 1 з 5', $view['title']);
        self::assertSame([
            ['orderId' => 990061, 'kind' => 'created', 'text' => 'накладна 20 4500 0000 0011'],
            ['orderId' => 990063, 'kind' => 'skipped', 'text' => 'пропущено: накладна вже є (20 4500 0000 0003)'],
            ['orderId' => 990064, 'kind' => 'skipped', 'text' => 'пропущено: доставка не Новою Поштою'],
            ['orderId' => 990065, 'kind' => 'failed', 'text' => 'НП відмовила: Виправте телефон.'],
            ['orderId' => 990066, 'kind' => 'failed', 'text' => 'НП відмовила: Something unexpected.'],
        ], $view['rows']);
        self::assertSame('до 20; решту 8 не чіпали', $view['untouched']);
        self::assertNull(OrdersMassAction::view(new MassReport([], 0), static fn(string $key): string => $texts[$key])['untouched']);
    }
}
