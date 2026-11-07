<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

require_once __DIR__ . '/../Support/WaybillFixtures.php';

use Modules\Sviat\NovaPoshta\Support\WaybillFixtures as W;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;
use Okay\Modules\Sviat\NovaPoshta\Waybills\NewShipmentRequest;
use Okay\Modules\Sviat\NovaPoshta\Waybills\OldDocumentRequest;
use PHPUnit\Framework\TestCase;

class WaybillRequestTest extends TestCase
{
    private const array COMMON = [
        'PayerType' => 'Recipient',
        'PaymentMethod' => 'Cash',
        'CargoType' => 'Parcel',
        'SeatsAmount' => '1',
        'Description' => 'Товари інтернет-магазину',
        'Cost' => '2359',
        'Weight' => '4',
        'VolumeGeneral' => '0.008',
        'DateTime' => '07.11.2026',
        'Sender' => '00000000-0000-0000-0000-000000000001',
        'ContactSender' => '00000000-0000-0000-0000-000000000010',
        'SendersPhone' => '380670000000',
        'SenderWarehouseIndex' => '74/1',
        'RecipientsPhone' => '380500000000',
        'InfoRegClientBarcodes' => '990061',
    ];

    public function testNewShipment(): void
    {
        self::assertSame([
            'payerType' => 'Recipient',
            'sender' => ['countryCode' => 'UA', 'divisionId' => 5524, 'name' => 'Відправник Тестовий Тестович', 'phone' => '380670000000'],
            'recipient' => ['countryCode' => 'UA', 'divisionId' => 7162, 'name' => 'Петренко Іван', 'phone' => '380500000000'],
            'parcels' => [[
                'rowNumber' => 1,
                'cargoCategory' => 'parcel',
                'parcelDescription' => 'Товари інтернет-магазину',
                'insuranceCost' => 2359.0,
                'length' => 200,
                'width' => 200,
                'height' => 200,
                'actualWeight' => 4000,
            ]],
        ], NewShipmentRequest::build(W::point(), W::sender()));
    }

    public function testOldDivisionWithCod(): void
    {
        self::assertSame(self::COMMON + [
            'NewAddress' => '1',
            'ServiceType' => 'WarehouseWarehouse',
            'RecipientWarehouseIndex' => '11/1',
            'RecipientName' => 'Петренко Іван',
            'RecipientType' => 'PrivatePerson',
            'RecipientContactName' => 'Петренко Іван',
            'BackwardDeliveryData' => [['PayerType' => 'Recipient', 'CargoType' => 'Money', 'RedeliveryString' => '2454']],
        ], OldDocumentRequest::forDivision(W::point(['cod' => true]), W::sender()));
    }

    public function testOldControl(): void
    {
        $request = OldDocumentRequest::forDivision(W::point(['cod' => true, 'codKind' => CodKind::Control]), W::sender());

        self::assertSame('2454', $request['AfterpaymentOnGoodsCost']);
        self::assertArrayNotHasKey('BackwardDeliveryData', $request);
    }

    public function testOldWithoutCodHasNoMoneyServices(): void
    {
        $request = OldDocumentRequest::forDivision(W::point(['paymentMethod' => 'NonCash']), W::sender());

        self::assertSame('NonCash', $request['PaymentMethod']);
        self::assertArrayNotHasKey('BackwardDeliveryData', $request);
        self::assertArrayNotHasKey('AfterpaymentOnGoodsCost', $request);
    }

    public function testOldAddressRef(): void
    {
        $plain = OldDocumentRequest::forAddressRef(W::address(), W::sender(), 'city-ref', 'recipient-ref', 'contact-ref', 'address-ref');
        self::assertSame(self::COMMON + [
            'ServiceType' => 'WarehouseDoors',
            'CityRecipient' => 'city-ref',
            'RecipientAddress' => 'address-ref',
            'Recipient' => 'recipient-ref',
            'ContactRecipient' => 'contact-ref',
            'RecipientType' => 'PrivatePerson',
        ], $plain);

        $lifting = OldDocumentRequest::forAddressRef(W::address(['lifting' => true, 'floor' => 5, 'elevator' => true]), W::sender(), 'city-ref', 'recipient-ref', 'contact-ref', 'address-ref');
        self::assertSame('5', $lifting['NumberOfFloorsLifting']);
        self::assertSame('1', $lifting['Elevator']);
    }

    public function testOldAddressText(): void
    {
        $request = OldDocumentRequest::forAddressText(W::address(['districtName' => 'Броварський район', 'settlementName' => 'село Велика Димерка']), W::sender());

        self::assertSame(self::COMMON + [
            'NewAddress' => '1',
            'ServiceType' => 'WarehouseDoors',
            'RecipientCityName' => 'Велика Димерка',
            'RecipientArea' => 'Київська',
            'RecipientAreaRegions' => 'Броварський',
            'RecipientAddressName' => 'вул. Хрещатик',
            'RecipientHouse' => '22',
            'RecipientFlat' => '5',
            'RecipientAddressNote' => "під'їзд 2",
            'RecipientName' => 'Петренко Іван',
            'RecipientType' => 'PrivatePerson',
            'RecipientContactName' => 'Петренко Іван',
        ], $request);
    }

    public function testGuard(): void
    {
        self::assertNull(OldDocumentRequest::guard(OldDocumentRequest::forDivision(W::point(), W::sender())));
        self::assertNull(OldDocumentRequest::guard(OldDocumentRequest::forAddressText(W::address(), W::sender())));
        self::assertNull(OldDocumentRequest::guard(OldDocumentRequest::forAddressRef(W::address(), W::sender(), 'c', 'r', 'p', 'a')));

        $mixed = OldDocumentRequest::forAddressRef(W::address(), W::sender(), 'c', 'r', 'p', 'a') + ['RecipientCityName' => 'Київ'];
        self::assertSame('waybill_guard_address', OldDocumentRequest::guard($mixed));
        $noIndex = OldDocumentRequest::forDivision(W::point(), W::sender());
        unset($noIndex['RecipientWarehouseIndex']);
        self::assertSame('waybill_guard_point', OldDocumentRequest::guard($noIndex));
        self::assertSame('waybill_guard_point', OldDocumentRequest::guard(['ServiceType' => 'DoorsDoors']));
    }

    private const array ORG = ['recipientType' => 'Organization', 'edrpou' => '12345678', 'companyName' => 'ТОВ «Приклад»'];

    public function testOldOrganizationOnPoint(): void
    {
        $request = OldDocumentRequest::forDivision(W::point(self::ORG), W::sender());

        self::assertSame(['Organization', '12345678', 'ТОВ «Приклад»', 'Петренко Іван'], [$request['RecipientType'], $request['EDRPOU'], $request['RecipientName'], $request['RecipientContactName']]);
        self::assertArrayNotHasKey('Recipient', $request);
        self::assertArrayNotHasKey('OwnershipForm', $request);
        self::assertNull(OldDocumentRequest::guard($request));
        self::assertSame('Петренко Іван', OldDocumentRequest::forDivision(W::point(['companyName' => null] + self::ORG), W::sender())['RecipientName']);
    }

    public function testOldOrganizationByText(): void
    {
        $request = OldDocumentRequest::forAddressText(W::address(self::ORG), W::sender(), 'form-ref');

        self::assertSame(['Organization', '12345678', 'form-ref'], [$request['RecipientType'], $request['EDRPOU'], $request['OwnershipForm']]);
        self::assertNull(OldDocumentRequest::guard($request));
        self::assertArrayNotHasKey('OwnershipForm', OldDocumentRequest::forAddressText(W::address(), W::sender(), 'form-ref'));
    }

    public function testOldOrganizationByRef(): void
    {
        $request = OldDocumentRequest::forAddressRef(W::address(self::ORG), W::sender(), 'c', 'r', 'p', 'a');

        self::assertSame('Organization', $request['RecipientType']);
        self::assertArrayNotHasKey('EDRPOU', $request);
    }
}
