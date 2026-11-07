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
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Waybills\RecipientCities;
use Okay\Modules\Sviat\NovaPoshta\Waybills\SenderParty;
use Okay\Modules\Sviat\NovaPoshta\Waybills\SenderSource;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillService;
use PHPUnit\Framework\TestCase;

class WaybillServiceTest extends TestCase
{
    private const int EXP = 1_900_003_600;
    private const int ORDER = 990061;

    private FakeTransport $http;
    private InMemoryWaybillRows $rows;
    private SenderParty|string $sender;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->rows = new InMemoryWaybillRows();
        $this->sender = W::sender();
    }

    private function service(): WaybillService
    {
        $key = F::KEY;
        $apiKey = F::apiKey($this, $key);
        $noSleep = static function (): void {
        };
        $jwt = new JwtProvider($this->http, new InMemoryState(), $apiKey, static fn(): int => self::EXP - 3600);
        $sender = new class ($this->sender) implements SenderSource {
            public function __construct(private SenderParty|string $party)
            {
            }

            public function party(): SenderParty|string
            {
                return $this->party;
            }
        };
        $cities = new class implements RecipientCities {
            public function cityRef(int $settlementId): ?string
            {
                return $settlementId === 118064 ? 'city-ref' : null;
            }
        };

        return new WaybillService(new OldApiClient($this->http, $apiKey, $noSleep), new NewApiClient($this->http, $jwt, $noSleep), $this->rows, $sender, $cities, static fn(): string => '07.11.2026');
    }

    private function jwt(): void
    {
        $this->http->json(200, ['jwt' => F::jwt(self::EXP)]);
    }

    private function statusAnswer(string $fixture): void
    {
        $this->http->json(200, ['success' => true, 'data' => [W::fixture($fixture)], 'errors' => []]);
    }

    private function requestBody(int $index): array
    {
        return json_decode($this->http->requests()[$index]->body, true);
    }

    public function testPointWithoutCodGoesToNewApi(): void
    {
        $this->jwt();
        $this->http->json(201, W::fixture('new_created'));
        $this->statusAnswer('status_new_documents');

        $outcome = $this->service()->create(self::ORDER, W::point());

        self::assertTrue($outcome->ok);
        $row = $this->rows->forOrder(self::ORDER);
        self::assertSame('new', $row->api);
        self::assertSame(W::fixture('new_created')['id'], $row->ref);
        self::assertSame(W::fixture('new_created')['number'], $outcome->number);
        self::assertNotNull($row->audit_status);
        self::assertCount(3, $this->http->requests());
        self::assertSame('https://api.novaposhta.ua/v.1.0/shipments', $this->http->requests()[1]->url);
        self::assertSame('380500000000', $this->requestBody(2)['methodProperties']['Documents'][0]['Phone']);
    }

    public function testCodGoesToOldApi(): void
    {
        $this->http->json(200, W::fixture('old_save_ok'));
        $this->statusAnswer('status_cod');

        $outcome = $this->service()->create(self::ORDER, W::point(['cod' => true, 'codAmount' => 500.0, 'codPayer' => 'Sender']));

        self::assertTrue($outcome->ok);
        self::assertSame('old', $this->rows->forOrder(self::ORDER)->api);
        self::assertSame('InternetDocumentGeneral', $this->requestBody(0)['modelName']);
        self::assertSame('verified', $outcome->audit->status);
        self::assertSame('verified', $this->rows->forOrder(self::ORDER)->audit_status);
    }

    public function testTransportErrorOnSaveIsUncertain(): void
    {
        $this->http->push(new TransportException('timeout'));

        $outcome = $this->service()->create(self::ORDER, W::point(['cod' => true]));

        self::assertFalse($outcome->ok);
        self::assertSame('waybill_uncertain', $outcome->issues[0]['advice']);
        self::assertNull($this->rows->forOrder(self::ORDER));
        self::assertCount(1, $this->http->requests());
    }

    public function testRefusalIsExplained(): void
    {
        $this->http->json(200, W::fixture('old_save_error'));

        $outcome = $this->service()->create(self::ORDER, W::point(['cod' => true]));

        self::assertFalse($outcome->ok);
        self::assertSame('recipient_name', $outcome->issues[0]['field']);
        self::assertNull($this->rows->forOrder(self::ORDER));
    }

    public function testSecondCreateIsRefusedWithoutRequest(): void
    {
        $this->rows->add(self::ORDER, '20450000000001', 'ref', 'old', []);

        $outcome = $this->service()->create(self::ORDER, W::point());

        self::assertFalse($outcome->ok);
        self::assertSame('waybill_already', $outcome->issues[0]['advice']);
        self::assertSame('20450000000001', $outcome->issues[0]['message']);
        self::assertSame([], $this->http->requests());
    }

    public function testSenderProblemStopsBeforeNovaPoshta(): void
    {
        $this->sender = 'waybill_no_contact';

        $outcome = $this->service()->create(self::ORDER, W::point());

        self::assertSame('waybill_no_contact', $outcome->issues[0]['advice']);
        self::assertSame([], $this->http->requests());
    }

    public function testAuditFailureKeepsWaybill(): void
    {
        $this->http->json(200, W::fixture('old_save_ok'));
        $this->http->json(502, []);

        $outcome = $this->service()->create(self::ORDER, W::point(['cod' => true]));

        self::assertTrue($outcome->ok);
        self::assertSame('unverified', $outcome->audit->status);
        self::assertSame('unverified', $this->rows->forOrder(self::ORDER)->audit_status);
    }

    public function testAddressByRef(): void
    {
        $this->http->json(200, W::fixture('counterparty_save'));
        $this->http->json(200, W::fixture('address_save'));
        $this->http->json(200, W::fixture('old_save_address_ref'));
        $this->statusAnswer('status_address');

        $outcome = $this->service()->create(self::ORDER, W::address());

        self::assertTrue($outcome->ok);
        self::assertSame(['waybill_notice_cargo_changed'], $outcome->notices);
        $save = $this->requestBody(2)['methodProperties'];
        self::assertSame('city-ref', $save['CityRecipient']);
        self::assertSame(W::fixture('address_save')['data'][0]['Ref'], $save['RecipientAddress']);
        self::assertSame(['Іван', 'Петренко'], [$this->requestBody(0)['methodProperties']['FirstName'], $this->requestBody(0)['methodProperties']['LastName']]);
        self::assertSame("під'їзд 2", $this->requestBody(1)['methodProperties']['Note']);
    }

    public function testAddressFallsBackToText(): void
    {
        $this->http->json(200, W::fixture('counterparty_save'));
        $this->http->json(200, ['success' => false, 'data' => [], 'errors' => ['Street not found'], 'errorCodes' => ['20000900000']]);
        $this->http->json(200, W::fixture('old_save_ok'));
        $this->statusAnswer('status_address_text');

        $outcome = $this->service()->create(self::ORDER, W::address());

        self::assertTrue($outcome->ok);
        self::assertContains('waybill_text_address', $outcome->notices);
        self::assertSame('1', $this->requestBody(2)['methodProperties']['NewAddress']);
        self::assertSame('вул. Хрещатик', $this->requestBody(2)['methodProperties']['RecipientAddressName']);
    }

    public function testAddressWithoutStreetRefIsTextWithoutLookups(): void
    {
        $this->http->json(200, W::fixture('old_save_ok'));
        $this->statusAnswer('status_address_text');

        $outcome = $this->service()->create(self::ORDER, W::address(['streetRef' => null]));

        self::assertContains('waybill_text_address', $outcome->notices);
        self::assertSame('InternetDocumentGeneral', $this->requestBody(0)['modelName']);
    }

    public function testAttach(): void
    {
        self::assertSame('waybill_bad_number', $this->service()->attach(self::ORDER, '123', '380500000000')->issues[0]['advice']);

        $this->rows->add(1, '20451581331178', null, 'attached', []);
        $elsewhere = $this->service()->attach(self::ORDER, '2045 1581 3311 78', '380500000000');
        self::assertSame('waybill_attached_elsewhere', $elsewhere->issues[0]['advice']);
        self::assertSame('1', $elsewhere->issues[0]['message']);
        self::assertSame([], $this->http->requests());

        $this->http->json(200, W::fixture('status_unknown'));
        self::assertSame('waybill_not_found', $this->service()->attach(self::ORDER, '20450000000001', '380500000000')->issues[0]['advice']);

        $this->statusAnswer('status_cod');
        $ok = $this->service()->attach(self::ORDER, '20451581317746', '380500000000');
        self::assertTrue($ok->ok);
        self::assertSame('attached', $this->rows->forOrder(self::ORDER)->api);
        self::assertNull($this->rows->forOrder(self::ORDER)->ref);
    }

    public function testDeletedWaybillIsNotAttached(): void
    {
        $this->http->json(200, ['success' => true, 'data' => [['StatusCode' => '2', 'Status' => 'Видалено'] + W::fixture('status_cod')], 'errors' => []]);

        self::assertSame('waybill_deleted', $this->service()->attach(self::ORDER, '20451581317746', '380500000000')->issues[0]['advice']);
        self::assertNull($this->rows->forOrder(self::ORDER));
    }

    public function testRemove(): void
    {
        $this->rows->add(1, '20450000000001', '01a1b60d-0000-0000-0000-000000000001', 'new', []);
        $this->rows->add(2, '20450000000002', '01a1b60d-0000-0000-0000-000000000002', 'old', []);
        $this->rows->add(3, '20450000000003', null, 'attached', []);
        $this->jwt();
        $this->http->json(200, ['success' => true]);
        $this->http->json(200, ['success' => true, 'data' => [['Ref' => '01a1b60d-0000-0000-0000-000000000002']], 'errors' => []]);

        self::assertTrue($this->service()->remove(1)->ok);
        self::assertTrue($this->service()->remove(2)->ok);
        self::assertTrue($this->service()->remove(3)->ok);

        self::assertSame('DELETE', $this->http->requests()[1]->method);
        self::assertSame('https://api.novaposhta.ua/v.1.0/shipments/01a1b60d-0000-0000-0000-000000000001', $this->http->requests()[1]->url);
        self::assertSame(['DocumentRefs' => '01a1b60d-0000-0000-0000-000000000002'], $this->requestBody(2)['methodProperties']);
        self::assertCount(3, $this->http->requests());
        self::assertSame([], $this->rows->rows);
    }

    public function testRefusedRemoveKeepsTheRow(): void
    {
        $this->rows->add(2, '20450000000002', 'ref-2', 'old', []);
        $this->http->json(200, ['success' => false, 'data' => [], 'errors' => ['Document can not be deleted'], 'errorCodes' => ['20000100000']]);

        $outcome = $this->service()->remove(2);

        self::assertFalse($outcome->ok);
        self::assertNotNull($this->rows->forOrder(2));
        self::assertStringContainsString('20000100000', $outcome->issues[0]['details']);
    }
}
