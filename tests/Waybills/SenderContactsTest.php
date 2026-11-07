<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

require_once __DIR__ . '/../Support/FakeTransport.php';
require_once __DIR__ . '/../Support/ApiFixtures.php';
require_once __DIR__ . '/../Support/InMemoryState.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Modules\Sviat\NovaPoshta\Support\FakeTransport;
use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Waybills\SenderContacts;
use Okay\Modules\Sviat\NovaPoshta\Waybills\SenderContactsUnavailable;
use PHPUnit\Framework\TestCase;

class SenderContactsTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../Support/fixtures/waybill/';

    private FakeTransport $http;
    private InMemoryState $state;
    private int $now = 1_900_000_000;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->state = new InMemoryState();
    }

    private function contacts(): SenderContacts
    {
        $key = F::KEY;
        $api = new OldApiClient($this->http, F::apiKey($this, $key), static function (): void {
        });

        return new SenderContacts($api, $this->state, fn(): int => $this->now);
    }

    private function answerPair(): void
    {
        $sender = json_decode(file_get_contents(self::FIXTURES . 'sender.json'), true);
        $this->http->json(200, ['success' => true, 'data' => [$sender], 'errors' => []]);
        $this->http->json(200, json_decode(file_get_contents(self::FIXTURES . 'contacts.json'), true));
    }

    public function testSenderAndContactsComeFromNovaPoshtaOnce(): void
    {
        $this->answerPair();

        $first = $this->contacts();
        self::assertSame('00000000-0000-0000-0000-000000000001', $first->sender()['ref']);
        self::assertSame([['ref' => '00000000-0000-0000-0000-000000000010', 'name' => 'Відправник Тестовий Тестович', 'phone' => '380670000000']], $first->contacts());
        self::assertSame($first->contacts(), $this->contacts()->contacts());
        self::assertCount(2, $this->http->requests());
    }

    public function testCacheExpiresAfterAnHour(): void
    {
        $this->answerPair();
        $this->contacts()->contacts();
        $this->now += 3601;
        $this->answerPair();

        $this->contacts()->contacts();

        self::assertCount(4, $this->http->requests());
    }

    public function testNoContactsIsAnEmptyList(): void
    {
        $sender = json_decode(file_get_contents(self::FIXTURES . 'sender.json'), true);
        $this->http->json(200, ['success' => true, 'data' => [$sender], 'errors' => []]);
        $this->http->json(200, ['success' => true, 'data' => [], 'errors' => []]);

        self::assertSame([], $this->contacts()->contacts());
    }

    public function testNovaPoshtaFailureIsReportedWithoutTheKey(): void
    {
        $this->http->json(200, ['success' => false, 'data' => [], 'errors' => ['API key ' . F::KEY . ' expired'], 'errorCodes' => ['20000201204']]);

        try {
            $this->contacts()->contacts();
            self::fail('expected SenderContactsUnavailable');
        } catch (SenderContactsUnavailable $e) {
            self::assertStringNotContainsString(F::KEY, $e->getMessage());
        }
    }
}
