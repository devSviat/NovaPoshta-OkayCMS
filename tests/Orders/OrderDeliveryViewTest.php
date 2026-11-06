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

        self::assertSame(['city' => 'місто Бровари, Броварський район, Київська область', 'point' => 'Відділення №3: вул. Київська, 1', 'term' => null, 'priceUnconfirmed' => false], $lines);
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

        self::assertSame(['city' => 'місто Київ', 'point' => 'Поштомат №5', 'term' => null, 'priceUnconfirmed' => false], $lines);
    }

    public function testTermAndUnconfirmedPrice(): void
    {
        $lines = OrderDeliveryView::lines((object)['settlement_name' => 'місто Київ', 'division_name' => 'Відділення №2', 'delivery_term' => '2030-03-18', 'price_source' => 'fallback']);

        self::assertSame('18.03.2030', $lines['term']);
        self::assertTrue($lines['priceUnconfirmed']);
        self::assertNull(OrderDeliveryView::lines((object)['settlement_name' => 'x', 'delivery_term' => 'garbage'])['term']);
    }
}
