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
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillTracking;
use PHPUnit\Framework\TestCase;

class WaybillTrackingTest extends TestCase
{
    private const int NOW = 1_900_000_000;
    private const int EXP = 1_900_003_600;

    private FakeTransport $http;
    private InMemoryWaybillRows $rows;
    private InMemoryState $state;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->rows = new InMemoryWaybillRows();
        $this->rows->now = self::NOW;
        $this->state = new InMemoryState();
    }

    private function tracking(): WaybillTracking
    {
        $key = F::KEY;
        $apiKey = F::apiKey($this, $key);
        $noSleep = static function (): void {
        };
        $jwt = new JwtProvider($this->http, new InMemoryState(), $apiKey, static fn(): int => self::EXP - 3600);

        return new WaybillTracking(new NewApiClient($this->http, $jwt, $noSleep), new OldApiClient($this->http, $apiKey, $noSleep), $this->rows, $this->state, static fn(): int => self::NOW);
    }

    private function waybill(int $orderId, ?int $status = null, int $ageDays = 1): void
    {
        $this->rows->add($orderId, sprintf('2045%010d', $orderId), 'ref-' . $orderId, 'old', []);
        $this->rows->rows[$orderId]->created_at = date('Y-m-d H:i:s', self::NOW - $ageDays * 86400);
        $this->rows->rows[$orderId]->status_code = $status;
    }

    private static function item(string $number, int $code, string $text): array
    {
        return ['currentStatus' => ['number' => $number, 'statusCode' => $code, 'status' => $text, 'statusDate' => '2026-11-07T12:47:53.537412Z', 'scheduledDate' => '0001-01-01T00:00:00.000000Z']];
    }

    public function testBatchSkipsFinalAndCapsRequests(): void
    {
        for ($i = 1; $i <= 1200; $i++) {
            $this->waybill($i);
        }
        for ($i = 2001; $i <= 2050; $i++) {
            $this->waybill($i, 9);
        }
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)]);
        for ($i = 0; $i < 10; $i++) {
            $this->http->json(200, ['items' => []]);
        }

        $result = $this->tracking()->tick();

        self::assertSame(10, $result['requests']);
        $urls = array_map(static fn($r): string => $r->url, array_slice($this->http->requests(), 1));
        self::assertCount(10, $urls);
        self::assertSame(100, substr_count($urls[0], 'numbers%5B%5D='));
        self::assertStringNotContainsString('204500000020', implode(' ', $urls));
    }

    /** A waybill stuck for two months (made and never sent, an endless return) no longer slows the queue. */
    public function testOldUnfinishedWaybillsAreNoLongerAsked(): void
    {
        $this->waybill(7, 1, 61);
        $this->waybill(8, 4, 59);
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)]);
        $this->http->json(200, ['items' => []]);

        $this->tracking()->tick();

        $url = $this->http->requests()[1]->url;
        self::assertStringContainsString('20450000000008', $url);
        self::assertStringNotContainsString('20450000000007', $url);
    }

    public function testStatusIsSaved(): void
    {
        $this->waybill(7);
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)]);
        $this->http->json(200, ['items' => [self::item('20450000000007', 7, 'Прибуло у відділення')]]);

        $result = $this->tracking()->tick();

        self::assertSame(['checked' => 1, 'updated' => 1, 'requests' => 1], $result);
        self::assertSame(7, $this->rows->rows[7]->status_code);
        self::assertSame('Прибуло у відділення', $this->rows->rows[7]->status_text);
        self::assertNull($this->rows->rows[7]->scheduled_at);
        self::assertNotNull($this->rows->rows[7]->status_at);
    }

    public function testMissingNumberIsLeftAlone(): void
    {
        $this->waybill(5);
        $this->waybill(6, null, 31);
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)]);
        $this->http->json(200, ['items' => []]);

        $this->tracking()->tick();

        self::assertNull($this->rows->rows[5]->status_code);
        self::assertNotNull($this->rows->rows[5]->tracked_at);
        self::assertSame(3, $this->rows->rows[6]->status_code);
    }

    public function testFailureChangesNothing(): void
    {
        $this->waybill(8);
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)]);
        $this->http->json(422, ['errors' => ['numbers' => 'bad']]);

        $result = $this->tracking()->tick();

        self::assertSame(1, $result['requests']);
        self::assertNull($this->rows->rows[8]->status_code);
        self::assertNull($this->rows->rows[8]->tracked_at ?? null);
        self::assertNull($this->state->get('tracking_running'));
    }

    public function testSecondRunExits(): void
    {
        $this->waybill(9);
        $this->state->set('tracking_running', (string)(self::NOW - 60));

        self::assertSame(['checked' => 0, 'updated' => 0, 'requests' => 0], $this->tracking()->tick());
        self::assertSame([], $this->http->requests());
    }

    public function testRefreshStoresTheFullDocument(): void
    {
        $this->waybill(10);
        $this->http->json(200, ['success' => true, 'data' => [W::fixture('status_cod')], 'errors' => []]);

        $outcome = $this->tracking()->refresh(10, '380500000000');

        self::assertTrue($outcome->ok);
        self::assertSame(1, $this->rows->rows[10]->status_code);
        self::assertSame(W::fixture('status_cod')['Number'], json_decode($this->rows->rows[10]->tracking, true)['Number']);
        self::assertSame('380500000000', json_decode($this->http->requests()[0]->body, true)['methodProperties']['Documents'][0]['Phone']);
    }

    public function testRefreshUsesTheWaybillsRecipientPhone(): void
    {
        $this->waybill(12);
        $this->rows->saveTracking(12, ['StatusCode' => '1', 'PhoneRecipient' => '380500000000']);
        $this->http->json(200, ['success' => true, 'data' => [W::fixture('status_cod')], 'errors' => []]);

        $this->tracking()->refresh(12, '380501234567');

        self::assertSame('380500000000', json_decode($this->http->requests()[0]->body, true)['methodProperties']['Documents'][0]['Phone']);
    }

    public function testNotFoundAnswerDoesNotStopTracking(): void
    {
        $this->waybill(13);
        $this->rows->saveTracking(13, ['StatusCode' => '3', 'Status' => 'Номер не знайдено']);
        self::assertNull($this->rows->rows[13]->status_code);

        $this->http->json(200, ['jwt' => F::jwt(self::EXP)]);
        $this->http->json(200, ['items' => [self::item('20450000000013', 3, 'Not found')]]);
        $this->tracking()->tick();

        self::assertNull($this->rows->rows[13]->status_code);
        self::assertSame([13], array_map(static fn($r): int => (int)$r->order_id, $this->rows->due(10, 1, '2000-01-01 00:00:00')));
    }

    public function testRefreshKeepsTheRecipientPhoneWhenNovaPoshtaHidesIt(): void
    {
        $this->waybill(14);
        $this->rows->saveTracking(14, ['StatusCode' => '1', 'PhoneRecipient' => '380500000000']);
        $this->http->json(200, ['success' => true, 'data' => [W::fixture('status_hidden')], 'errors' => []]);
        $this->tracking()->refresh(14, '380501234567');

        self::assertSame('380500000000', json_decode($this->rows->rows[14]->tracking, true)['PhoneRecipient']);
    }

    public function testRefreshWithoutPhoneExplains(): void
    {
        $this->waybill(11);
        $this->http->json(200, ['success' => true, 'data' => [W::fixture('status_hidden')], 'errors' => []]);

        $outcome = $this->tracking()->refresh(11, null);

        self::assertTrue($outcome->ok);
        self::assertSame(['waybill_tracking_no_phone'], $outcome->notices);
    }
}
