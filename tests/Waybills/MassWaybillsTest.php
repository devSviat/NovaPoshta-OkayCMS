<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

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
use Okay\Modules\Sviat\NovaPoshta\Waybills\MassOrders;
use Okay\Modules\Sviat\NovaPoshta\Waybills\MassReport;
use Okay\Modules\Sviat\NovaPoshta\Waybills\MassWaybills;
use Okay\Modules\Sviat\NovaPoshta\Waybills\RecipientCities;
use Okay\Modules\Sviat\NovaPoshta\Waybills\SenderParty;
use Okay\Modules\Sviat\NovaPoshta\Waybills\SenderSource;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillDraft;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillService;
use PHPUnit\Framework\TestCase;

class MassWaybillsTest extends TestCase
{
    private FakeTransport $http;
    private InMemoryWaybillRows $rows;
    /** @var array<int, WaybillDraft|string> */
    private array $drafts = [];
    /** @var list<int> */
    private array $notOurs = [];
    /** @var list<float> */
    private array $ticks = [];

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->rows = new InMemoryWaybillRows();
    }

    private function mass(): MassWaybills
    {
        $orders = new class ($this) implements MassOrders {
            public function __construct(private MassWaybillsTest $test)
            {
            }

            public function isOurs(int $orderId): bool
            {
                return $this->test->isOurs($orderId);
            }

            public function draft(int $orderId): WaybillDraft|string
            {
                return $this->test->draft($orderId);
            }
        };
        $clock = $this->ticks === [] ? null : function (): float {
            return count($this->ticks) > 1 ? array_shift($this->ticks) : $this->ticks[0];
        };

        return new MassWaybills($this->service(), $this->rows, $orders, $clock);
    }

    private function service(): WaybillService
    {
        $key = F::KEY;
        $apiKey = F::apiKey($this, $key);
        $noSleep = static function (): void {
        };
        $jwt = new JwtProvider($this->http, new InMemoryState(), $apiKey, static fn(): int => 1_900_000_000);
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
        return new WaybillService(new OldApiClient($this->http, $apiKey, $noSleep), new NewApiClient($this->http, $jwt, $noSleep), $this->rows, $sender, $cities, static fn(): string => '07.11.2026', fn(): int => $this->rows->now);
    }

    public function isOurs(int $orderId): bool
    {
        return !in_array($orderId, $this->notOurs, true);
    }

    public function draft(int $orderId): WaybillDraft|string
    {
        return $this->drafts[$orderId] ?? W::point(['orderId' => $orderId, 'cod' => true, 'codAmount' => 500.0]);
    }

    private function npCreates(int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->http->json(200, W::fixture('old_save_ok'));
            $this->http->json(200, ['success' => true, 'data' => [W::fixture('status_cod')], 'errors' => []]);
        }
    }

    private function saves(): int
    {
        return count(array_filter($this->http->requests(), static fn($r): bool => str_contains($r->body, 'InternetDocumentGeneral')));
    }

    public function testIdsAreCleanedAndCapped(): void
    {
        $this->notOurs = range(1, 100);
        $raw = ['5', 'x', '5', '-1', ['7'], '0', ...array_map('strval', range(10, 34))];

        $report = $this->mass()->run($raw);

        self::assertSame(20, $report->processed());
        self::assertSame([5, ...range(10, 28)], array_column($report->rows, 'orderId'));
        self::assertSame(6, $report->untouched);
        self::assertSame(0, $this->saves());
    }

    public function testCreatedRowHasNumber(): void
    {
        $this->npCreates();

        $report = $this->mass()->run(['990061']);

        $number = W::fixture('old_save_ok')['data'][0]['IntDocNumber'];
        self::assertSame([['orderId' => 990061, 'kind' => 'created', 'key' => null, 'value' => $number]], $report->rows);
        self::assertSame(1, $report->created());
    }

    public function testNotOursIsSkipped(): void
    {
        $this->notOurs = [990062];

        self::assertSame([['orderId' => 990062, 'kind' => 'skipped', 'key' => 'mass_not_np', 'value' => null]], $this->mass()->run([990062])->rows);
    }

    public function testExistingIsSkippedWithNumber(): void
    {
        $this->rows->add(990063, '20450000000003', null, 'old', []);

        self::assertSame([['orderId' => 990063, 'kind' => 'skipped', 'key' => 'mass_has_waybill', 'value' => '20450000000003']], $this->mass()->run([990063])->rows);
        self::assertSame(0, $this->saves());
    }

    public function testPendingIsSkipped(): void
    {
        $this->rows->reserve(990064);

        self::assertSame('waybill_pending', $this->mass()->run([990064])->rows[0]['key']);
        self::assertSame(0, $this->saves());
    }

    public function testDraftProblemIsSkipped(): void
    {
        $this->drafts[990065] = 'waybill_no_point';

        self::assertSame([['orderId' => 990065, 'kind' => 'skipped', 'key' => 'waybill_no_point', 'value' => null]], $this->mass()->run([990065])->rows);
    }

    public function testBadInvoiceEdrpouIsSkipped(): void
    {
        $this->drafts[990066] = W::point(['recipientType' => 'Organization', 'edrpou' => '123456789', 'fromInvoice' => true]);

        self::assertSame([['orderId' => 990066, 'kind' => 'skipped', 'key' => 'waybill_error_edrpou', 'value' => null]], $this->mass()->run([990066])->rows);
        self::assertSame(0, $this->saves());
    }

    public function testNpRefusalIsFailedWithMessage(): void
    {
        $answer = W::fixture('old_save_error');
        $this->http->json(200, $answer);

        $row = $this->mass()->run([990067])->rows[0];

        self::assertSame(['failed', 'waybill_advice_recipient_name', $answer['errors'][0]], [$row['kind'], $row['key'], $row['value']]);
        self::assertNull($this->rows->forOrder(990067));
    }

    public function testBudgetStopsStarting(): void
    {
        $this->notOurs = [1, 2, 3, 4];
        $this->ticks = [0.0, 0.0, MassWaybills::BUDGET_SECONDS - 1, MassWaybills::BUDGET_SECONDS + 1];

        $report = $this->mass()->run([1, 2, 3, 4]);

        self::assertSame([1, 2], array_column($report->rows, 'orderId'));
        self::assertSame(2, $report->untouched);
    }

    public function testSecondRunSkipsCreated(): void
    {
        $this->npCreates();

        $this->mass()->run([990061]);
        $second = $this->mass()->run([990061]);

        self::assertSame('mass_has_waybill', $second->rows[0]['key']);
        self::assertSame(1, $this->saves());
    }

    public function testReportSurvivesTheSession(): void
    {
        $report = new MassReport([['orderId' => 1, 'kind' => 'created', 'key' => null, 'value' => '20450000000001']], 3);

        self::assertEquals($report, MassReport::fromArray($report->toArray()));
        self::assertNull(MassReport::fromArray(['rows' => [['orderId' => 'x', 'kind' => 'evil']], 'untouched' => 1]));
        self::assertNull(MassReport::fromArray('junk'));
    }

    public function testBudgetFitsAProxyMinute(): void
    {
        self::assertLessThanOrEqual(45.0, MassWaybills::BUDGET_SECONDS);
    }

    public function testStaleAttemptIsReported(): void
    {
        $this->rows->reserve(990068);
        $this->rows->now += 600;
        $this->npCreates();

        $row = $this->mass()->run([990068])->rows[0];

        self::assertSame(['created', 'waybill_stale_attempt'], [$row['kind'], $row['key']]);
    }

    public function testUnexpectedErrorKeepsTheBatch(): void
    {
        $this->drafts[990069] = 'boom';
        $orders = new class implements MassOrders {
            public function isOurs(int $orderId): bool
            {
                return true;
            }

            public function draft(int $orderId): string
            {
                if ($orderId === 990069) {
                    throw new \RuntimeException('database went away');
                }

                return 'waybill_no_point';
            }
        };
        $report = new MassWaybills($this->service(), $this->rows, $orders)->run([990069, 990070]);

        self::assertSame([['orderId' => 990069, 'kind' => 'failed', 'key' => 'waybill_advice_generic', 'value' => null], ['orderId' => 990070, 'kind' => 'skipped', 'key' => 'waybill_no_point', 'value' => null]], $report->rows);
    }
}
