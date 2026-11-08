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

        return new WaybillService(new OldApiClient($this->http, $apiKey, $noSleep), new NewApiClient($this->http, $jwt, $noSleep), $this->rows, $sender, $cities, static fn(): string => '07.11.2026', fn(): int => $this->rows->now);
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
        self::assertSame(W::fixture('status_cod')['Number'], json_decode($this->rows->forOrder(self::ORDER)->tracking, true)['Number']);
        self::assertCount(2, $this->http->requests());
    }

    /** NP may have made the waybill: the order stays blocked until the manager checks, so no second one is made. */
    public function testTransportErrorOnSaveKeepsTheOrderForACheck(): void
    {
        $this->http->push(new TransportException('timeout'));

        $outcome = $this->service()->create(self::ORDER, W::point(['cod' => true]));

        self::assertFalse($outcome->ok);
        self::assertSame('waybill_uncertain', $outcome->issues[0]['advice']);
        self::assertTrue(WaybillService::needsCheck($this->rows->forOrder(self::ORDER), $this->rows->now));
        self::assertCount(1, $this->http->requests());

        $again = $this->service()->create(self::ORDER, W::point(['cod' => true]));
        self::assertSame('waybill_uncertain_check', $again->issues[0]['advice']);
        self::assertCount(1, $this->http->requests(), 'no second request to NP');
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

    public function testCreateInProgressIsRefusedWithoutRequest(): void
    {
        $this->rows->reserve(self::ORDER);
        $this->rows->now += 30;

        $outcome = $this->service()->create(self::ORDER, W::point());

        self::assertSame('waybill_pending', $outcome->issues[0]['advice']);
        self::assertSame([], $this->http->requests());
    }

    public function testLostReservationRaceIsRefusedWithoutRequest(): void
    {
        $this->rows->failReserve = true;

        $outcome = $this->service()->create(self::ORDER, W::point());

        self::assertFalse($outcome->ok);
        self::assertSame('waybill_pending', $outcome->issues[0]['advice']);
        self::assertSame([], $this->http->requests());
    }

    /** A request that died midway may have made the waybill too: checked first, never replaced blindly. */
    public function testStaleAttemptNeedsACheck(): void
    {
        $this->rows->reserve(self::ORDER);
        $this->rows->now += 600;

        $outcome = $this->service()->create(self::ORDER, W::point());

        self::assertSame('waybill_uncertain_check', $outcome->issues[0]['advice']);
        self::assertSame([], $this->http->requests());
    }

    private function documentList(array $documents): void
    {
        $this->http->json(200, ['success' => true, 'data' => $documents, 'errors' => []]);
    }

    public function testLookupAttachesTheWaybillNpMade(): void
    {
        $this->rows->reserve(self::ORDER);
        $this->rows->markUncertain(self::ORDER);
        $this->documentList([
            ['IntDocNumber' => '20450000000777', 'Ref' => 'other-ref', 'InfoRegClientBarcodes' => '990062', 'DeletionMark' => '0'],
            ['IntDocNumber' => '20450000000555', 'Ref' => 'deleted-ref', 'InfoRegClientBarcodes' => (string)self::ORDER, 'DeletionMark' => '1'],
            ['IntDocNumber' => '20450000000999', 'Ref' => 'made-ref', 'InfoRegClientBarcodes' => (string)self::ORDER, 'DeletionMark' => '0'],
        ]);
        $this->statusAnswer('status_new_documents');

        $outcome = $this->service()->lookup(self::ORDER, '380500000000');

        self::assertTrue($outcome->ok);
        self::assertSame('20450000000999', $outcome->number);
        $row = $this->rows->forOrder(self::ORDER);
        self::assertSame(['20450000000999', 'made-ref', 'old'], [$row->number, $row->ref, $row->api]);
        self::assertNull($row->audit_status ?? null, 'the check mark goes with the reservation');
        $sent = $this->requestBody(0);
        self::assertSame(['InternetDocument', 'getDocumentList'], [$sent['modelName'], $sent['calledMethod']]);
        self::assertSame('1', $sent['methodProperties']['GetFullList']);
    }

    public function testLookupWithNothingFreesTheOrder(): void
    {
        $this->rows->reserve(self::ORDER);
        $this->rows->markUncertain(self::ORDER);
        $this->documentList([]);

        $outcome = $this->service()->lookup(self::ORDER, '380500000000');

        self::assertTrue($outcome->ok);
        self::assertNull($outcome->number);
        self::assertSame(['waybill_lookup_none'], $outcome->notices);
        self::assertNull($this->rows->forOrder(self::ORDER), 'a new waybill can be made now');
    }

    public function testLookupWhileNpIsDownKeepsTheBlock(): void
    {
        $this->rows->reserve(self::ORDER);
        $this->rows->markUncertain(self::ORDER);
        $this->http->push(new TransportException('timeout'));

        $outcome = $this->service()->lookup(self::ORDER, '380500000000');

        self::assertFalse($outcome->ok);
        self::assertTrue(WaybillService::needsCheck($this->rows->forOrder(self::ORDER), $this->rows->now));
    }

    public function testLookupOnlyForAnUncertainOrder(): void
    {
        $this->rows->add(self::ORDER, '20450000000001', 'ref', 'old', []);

        self::assertSame('waybill_none', $this->service()->lookup(self::ORDER, null)->issues[0]['advice']);
        self::assertSame([], $this->http->requests());
    }

    public function testSaveFailureAfterNovaPoshtaNamesTheNumber(): void
    {
        $this->rows->failComplete = true;
        $this->http->json(200, W::fixture('old_save_ok'));

        $outcome = $this->service()->create(self::ORDER, W::point(['cod' => true]));

        self::assertFalse($outcome->ok);
        self::assertSame('waybill_not_saved', $outcome->issues[0]['advice']);
        self::assertSame(W::fixture('old_save_ok')['data'][0]['IntDocNumber'], $outcome->issues[0]['message']);
        self::assertCount(1, $this->http->requests());
    }

    public function testUnlinkKeepsTheWaybillAtNovaPoshta(): void
    {
        $this->rows->add(2, '20450000000002', 'ref-2', 'old', []);

        self::assertTrue($this->service()->unlink(2)->ok);
        self::assertNull($this->rows->forOrder(2));
        self::assertSame([], $this->http->requests());
        self::assertSame('waybill_none', $this->service()->unlink(2)->issues[0]['advice']);
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

    /** Two managers attach one number at once: the database lets one through; the other is told where it went. */
    public function testAttachLostToAnotherOrderSaysWhere(): void
    {
        $this->statusAnswer('status_cod');
        $this->rows->beforeAdd = static fn($rows) => $rows->add(77, '20451581317746', null, 'attached', []);

        $outcome = $this->service()->attach(self::ORDER, '20451581317746', '380500000000');

        self::assertFalse($outcome->ok);
        self::assertSame('waybill_attached_elsewhere', $outcome->issues[0]['advice']);
        self::assertSame('77', $outcome->issues[0]['message']);
        self::assertNull($this->rows->forOrder(self::ORDER));
    }

    /** NP made the waybill, but its number is already another order's: the shop learns the number, the reservation goes. */
    public function testCreatedNumberTakenByAnotherOrder(): void
    {
        $this->rows->add(77, W::fixture('old_save_ok')['data'][0]['IntDocNumber'], null, 'attached', []);
        $this->http->json(200, W::fixture('old_save_ok'));

        $outcome = $this->service()->create(self::ORDER, W::point(['cod' => true]));

        self::assertFalse($outcome->ok);
        self::assertSame('waybill_not_saved', $outcome->issues[0]['advice']);
        self::assertSame(W::fixture('old_save_ok')['data'][0]['IntDocNumber'], $outcome->issues[0]['message']);
        self::assertNull($this->rows->forOrder(self::ORDER), 'the reservation is released');
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

    private const array ORG = ['recipientType' => 'Organization', 'edrpou' => '12345678', 'companyName' => 'ТОВ «Приклад»'];

    public function testOrganizationOnPointGoesToTheOldApi(): void
    {
        $this->http->json(200, W::fixture('old_save_org'));
        $this->statusAnswer('status_org');

        $outcome = $this->service()->create(self::ORDER, W::point(self::ORG));

        self::assertTrue($outcome->ok);
        self::assertSame('old', $this->rows->forOrder(self::ORDER)->api);
        self::assertSame('12345678', $this->requestBody(0)['methodProperties']['EDRPOU']);
    }

    public function testOrganizationAddressByRef(): void
    {
        $this->http->json(200, W::fixture('counterparty_org_save'));
        $this->http->json(200, ['success' => true, 'data' => [['Ref' => 'contact-org']], 'errors' => []]);
        $this->http->json(200, W::fixture('address_save'));
        $this->http->json(200, W::fixture('old_save_org'));
        $this->statusAnswer('status_org_address');

        $outcome = $this->service()->create(self::ORDER, W::address(self::ORG));

        self::assertTrue($outcome->ok);
        self::assertSame([], $outcome->notices);
        $counterparty = $this->requestBody(0);
        self::assertSame(['Counterparty', 'save'], [$counterparty['modelName'], $counterparty['calledMethod']]);
        self::assertSame(['CounterpartyProperty' => 'Recipient', 'CounterpartyType' => 'Organization', 'EDRPOU' => '12345678'], $counterparty['methodProperties']);
        $contact = $this->requestBody(1);
        self::assertSame(['ContactPerson', 'save'], [$contact['modelName'], $contact['calledMethod']]);
        $recipientRef = W::fixture('counterparty_org_save')['data'][0]['Ref'];
        self::assertSame(['CounterpartyRef' => $recipientRef, 'FirstName' => 'Іван', 'LastName' => 'Петренко', 'Phone' => '380500000000'], $contact['methodProperties']);
        self::assertSame($recipientRef, $this->requestBody(2)['methodProperties']['CounterpartyRef']);
        $save = $this->requestBody(3)['methodProperties'];
        self::assertSame(['Organization', $recipientRef, 'contact-org'], [$save['RecipientType'], $save['Recipient'], $save['ContactRecipient']]);
    }

    public function testOrganizationAddressFallsBackToTextWithOwnershipForm(): void
    {
        $this->http->json(200, W::fixture('counterparty_org_save'));
        $this->http->json(200, ['success' => false, 'data' => [], 'errors' => ['Contact person creation problem'], 'errorCodes' => ['20000000000']]);
        $this->http->json(200, W::fixture('old_save_org'));
        $this->statusAnswer('status_org_address');

        $outcome = $this->service()->create(self::ORDER, W::address(self::ORG));

        self::assertTrue($outcome->ok);
        self::assertContains('waybill_text_address', $outcome->notices);
        $save = $this->requestBody(2)['methodProperties'];
        self::assertSame(['1', 'Organization', '12345678', W::fixture('counterparty_org_save')['data'][0]['OwnershipForm']], [$save['NewAddress'], $save['RecipientType'], $save['EDRPOU'], $save['OwnershipForm']]);
    }

    public function testRefusedEdrpouStopsBeforeTheWaybill(): void
    {
        $answer = W::fixture('old_save_org_inactive');
        $this->http->json(200, ['success' => false, 'data' => [], 'errors' => $answer['errors'], 'errorCodes' => $answer['errorCodes']]);

        $outcome = $this->service()->create(self::ORDER, W::address(self::ORG));

        self::assertFalse($outcome->ok);
        self::assertSame('edrpou', $outcome->issues[0]['field']);
        self::assertCount(1, $this->http->requests());
        self::assertNull($this->rows->forOrder(self::ORDER));
    }
}
