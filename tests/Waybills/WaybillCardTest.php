<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

require_once __DIR__ . '/../Support/WaybillFixtures.php';

use Modules\Sviat\NovaPoshta\Support\WaybillFixtures as W;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillCard;
use PHPUnit\Framework\TestCase;

class WaybillCardTest extends TestCase
{
    private static function row(array $override = []): object
    {
        return (object)($override + ['number' => '20451581331601', 'ref' => '01a1b60d-0000-0000-0000-000000000001', 'api' => 'old', 'status_code' => 7, 'tracked_at' => '2026-11-07 15:10:00']);
    }

    public function testFieldsComeFromTheDocument(): void
    {
        $card = WaybillCard::fromDocument(W::fixture('status_cod'), self::row());

        self::assertSame('20 4515 8133 1601', $card['numberSpaced']);
        self::assertSame('07.11.2026 15:02', $card['created']);
        self::assertSame('Тест', $card['description']);
        self::assertSame('500 ₴', $card['announced']);
        self::assertSame('м. Львів, Відділення №1: вул. Городоцька, 359', $card['route']['from']);
        // As the old tracking card: the date it leaves and the planned time with the hour.
        self::assertSame('07.11.2026', $card['route']['departure']);
        self::assertSame('08.11.2026 18:00', $card['route']['plan']);
        self::assertSame('м. Київ, Відділення №1: вул. Пирогівський шлях, 135', $card['route']['to']);
        self::assertSame('arrived', $card['route']['statusGroup']);
        self::assertSame('Петренко Іван', $card['recipient']['name']);
        self::assertSame('+380 50 000 00 00', $card['recipient']['phone']);
        self::assertSame('500 ₴', $card['services']['redelivery']);
        self::assertSame('sviat__novaposhta__waybill_party_Sender', $card['services']['redeliveryPayer']);
        self::assertNull($card['services']['control']);
        self::assertSame('200 ₴', $card['payment']['cost']);
        self::assertSame('sviat__novaposhta__waybill_party_Recipient', $card['payment']['payer']);
        self::assertSame('sviat__novaposhta__waybill_cash', $card['payment']['method']);
        self::assertSame('sviat__novaposhta__waybill_cargo_parcel', $card['more']['type']);
        self::assertSame('25 кг', $card['more']['weight']);
        self::assertSame('25 кг', $card['more']['volumeWeight']);
        self::assertSame('1', $card['more']['seats']);
        self::assertSame('2 кг', $card['more']['factualWeight']);
        self::assertSame('07.11.2026 15:10', $card['checked']);
    }

    public function testLinkDependsOnStatus(): void
    {
        self::assertSame('https://new.novaposhta.ua/edit/01a1b60d-0000-0000-0000-000000000001', WaybillCard::fromDocument(null, self::row(['status_code' => 1]))['link']);
        self::assertSame('https://novaposhta.ua/tracking/20451581331601/', WaybillCard::fromDocument(null, self::row())['link']);
        self::assertSame('https://novaposhta.ua/tracking/20451581331601/', WaybillCard::fromDocument(null, self::row(['status_code' => 1, 'ref' => null]))['link']);
    }

    public function testWithoutDocumentOnlyTheNumber(): void
    {
        $card = WaybillCard::fromDocument(null, self::row(['tracked_at' => null]));

        self::assertSame('20 4515 8133 1601', $card['numberSpaced']);
        self::assertNull($card['route']);
        self::assertNull($card['recipient']);
        self::assertNull($card['updated']);
    }

    public function testVolumeWeightFromGeneralVolume(): void
    {
        $card = WaybillCard::fromDocument(['VolumeGeneral' => '0.02', 'VolumeWeight' => '9'] + W::fixture('status_cod'), self::row());

        self::assertSame('5 кг', $card['more']['volumeWeight']);
    }

    public function testReturnOrRedirect(): void
    {
        $card = WaybillCard::fromDocument(['LastCreatedOnTheBasisDocumentType' => 'CargoReturn', 'LastCreatedOnTheBasisNumber' => '20450000000777'] + W::fixture('status_cod'), self::row());

        self::assertSame(['kind' => 'sviat__novaposhta__waybill_return', 'number' => '20450000000777'], $card['services']['returnOrRedirect']);
    }

    public function testStatusAndTimesFollowTheLatestCheck(): void
    {
        $card = WaybillCard::fromDocument(['_fetched_at' => '2026-11-05 10:00:00'] + W::fixture('status_cod'), self::row(['status_code' => 9, 'status_text' => 'Отримано', 'tracked_at' => '2026-11-07 15:10:00']));

        self::assertSame('Отримано', $card['route']['status']);
        self::assertSame('05.11.2026 10:00', $card['updated']);
        self::assertSame('07.11.2026 15:10', $card['checked']);
    }
}
