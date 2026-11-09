<?php

namespace Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryView;
use PHPUnit\Framework\TestCase;

class OrderDeliveryViewTest extends TestCase
{
    public function testCityCarriesDistrictAndAreaAndPointCarriesAddress(): void
    {
        $lines = OrderDeliveryView::lines((object)[
            'settlement_name' => 'місто Бровари',
            'district_name' => 'Броварський район',
            'area_name' => 'Київська область',
            'division_name' => 'Відділення №3',
            'division_address' => 'вул. Київська, 1',
        ]);

        self::assertSame(['city' => 'місто Бровари, Броварський район, Київська область', 'point' => 'Відділення №3: вул. Київська, 1', 'term' => null, 'priceUnconfirmed' => false, 'legacyUnmatched' => false, 'address' => null, 'lifting' => false, 'codFee' => null, 'codFeePublic' => true], $lines);
    }

    public function testMissingPartsAreLeftOut(): void
    {
        $lines = OrderDeliveryView::lines((object)[
            'settlement_name' => 'місто Київ',
            'district_name' => null,
            'area_name' => '',
            'division_name' => 'Поштомат №5',
            'division_address' => null,
        ]);

        self::assertSame(['city' => 'місто Київ', 'point' => 'Поштомат №5', 'term' => null, 'priceUnconfirmed' => false, 'legacyUnmatched' => false, 'address' => null, 'lifting' => false, 'codFee' => null, 'codFeePublic' => true], $lines);
    }

    public function testTermAndUnconfirmedPrice(): void
    {
        $lines = OrderDeliveryView::lines((object)['settlement_name' => 'місто Київ', 'division_name' => 'Відділення №2', 'delivery_term' => '2030-03-18', 'price_source' => 'fallback']);

        self::assertSame('18.03.2030', $lines['term']);
        self::assertTrue($lines['priceUnconfirmed']);
        self::assertNull(OrderDeliveryView::lines((object)['settlement_name' => 'x', 'delivery_term' => 'garbage'])['term']);
        // The column is DATETIME: the database gives the date back at midnight.
        self::assertSame('18.03.2030', OrderDeliveryView::lines((object)['settlement_name' => 'x', 'delivery_term' => '2030-03-18 00:00:00'])['term']);
    }

    public function testAddressParts(): void
    {
        $lines = OrderDeliveryView::lines((object)[
            'mode' => 'address', 'settlement_name' => 'місто Київ', 'area_name' => 'Київська область',
            'street_name' => 'вул. Хрещатик', 'street_ref' => '0f0d85b0-4143-11dd-9198-001d60451983', 'house' => '1', 'flat' => '5', 'entrance' => '', 'floor' => '7', 'elevator' => '1', 'address_note' => 'домофон 5', 'lifting' => '1',
        ]);

        // streetRef: the manager's address editor starts from it, so an unchanged street keeps its NP ref.
        self::assertSame(['street' => 'вул. Хрещатик', 'streetRef' => '0f0d85b0-4143-11dd-9198-001d60451983', 'house' => '1', 'flat' => '5', 'entrance' => null, 'floor' => 7, 'elevator' => true, 'note' => 'домофон 5'], $lines['address']);
        self::assertTrue($lines['lifting']);
        self::assertSame('', $lines['point']);
    }

    public function testPriceTheManagerEnteredLaterIsConfirmed(): void
    {
        $row = (object)['settlement_name' => 'місто Київ', 'price_source' => 'fallback'];

        self::assertTrue(OrderDeliveryView::lines($row, 0.0)['priceUnconfirmed']);
        self::assertFalse(OrderDeliveryView::lines($row, 85.0)['priceUnconfirmed']);
    }

    public function testFeeShownWhileCod(): void
    {
        $lines = OrderDeliveryView::lines((object)['settlement_name' => 'місто Київ', 'division_name' => 'Відділення №13', 'cod_fee' => '55.98'], 99.0, codNow: true);

        self::assertSame(55.98, $lines['codFee']);
    }

    public function testFeeHiddenOncePaymentIsNotCod(): void
    {
        $lines = OrderDeliveryView::lines((object)['settlement_name' => 'місто Київ', 'division_name' => 'Відділення №13', 'cod_fee' => '55.98'], 99.0, codNow: false);

        self::assertNull($lines['codFee'], 'the customer switched to a card on the order page');
    }

    public function testNoFeeColumn(): void
    {
        self::assertNull(OrderDeliveryView::lines((object)['settlement_name' => 'місто Київ'], 99.0, codNow: true)['codFee']);
        self::assertNull(OrderDeliveryView::lines((object)['settlement_name' => 'місто Київ', 'cod_fee' => '0.00'], 99.0, codNow: true)['codFee']);
    }

    public function testHiddenPriceKeepsFeeForTheShopOnly(): void
    {
        $lines = OrderDeliveryView::lines((object)['settlement_name' => 'місто Київ', 'cod_fee' => '55.98'], 99.0, codNow: true, pricePublic: false);

        self::assertSame([55.98, false], [$lines['codFee'], $lines['codFeePublic']]);
    }

    public function testPointNameIsShortened(): void
    {
        $lines = OrderDeliveryView::lines((object)['settlement_name' => 'місто Київ', 'division_name' => 'Поштомат "Нова Пошта" №1001', 'division_address' => 'вул. Сім\'ї Кульженків, 31а']);

        self::assertSame('Поштомат №1001: вул. Сім\'ї Кульженків, 31а', $lines['point']);
    }

    /** A branch name already carries its street («Відділення №2: вул. Богатирська, 11»): the address would repeat it. */
    public function testBranchAddressIsNotRepeated(): void
    {
        $lines = OrderDeliveryView::lines((object)['settlement_name' => 'місто Київ', 'division_name' => 'Відділення №2: вул. Богатирська, 11', 'division_address' => 'Київ, Богатирська, 11']);

        self::assertSame('Відділення №2: вул. Богатирська, 11', $lines['point']);
    }

    public function testWaybillLinkForTheCustomer(): void
    {
        $row = static fn(array $o): object => (object)($o + ['number' => '20451581331601', 'api' => 'old', 'status_code' => 7]);

        self::assertSame(
            ['number' => '20451581331601', 'numberSpaced' => '20 4515 8133 1601', 'url' => 'https://novaposhta.ua/tracking/20451581331601/'],
            \Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryView::waybill($row([])),
        );
        self::assertNull(\Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryView::waybill($row(['status_code' => 2])));
        self::assertNull(\Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryView::waybill($row(['api' => null, 'number' => null])));
        self::assertNull(\Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryView::waybill(null));
        self::assertNotNull(\Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryView::waybill($row(['status_code' => null, 'api' => 'attached'])));
    }
}
