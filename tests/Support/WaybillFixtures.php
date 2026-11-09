<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;
use Okay\Modules\Sviat\NovaPoshta\Waybills\SenderParty;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillDraft;

/** Чернетки й відправник зі значеннями, з якими звіряються тести запиту й аудиту. */
final class WaybillFixtures
{
    public static function point(array $override = []): WaybillDraft
    {
        return new WaybillDraft(
            990061, DeliveryMode::Points, 7162, '11/1', 'Відділення №1: вул. Пирогівський шлях, 135', 118064, 'місто Київ', 'Київська область', null,
            null, null, null, null, null, false, null, false,
            'Петренко Іван', '380500000000', 4.0, 0.008, 2359.0, 'Товари інтернет-магазину', 'Parcel', 'Recipient', 'Cash',
            false, 2454.0, CodKind::Money, 'Recipient', '07.11.2026',
        )->with($override);
    }

    public static function address(array $override = []): WaybillDraft
    {
        return self::point([
            'mode' => DeliveryMode::Address, 'divisionId' => null, 'divisionNumber' => null, 'divisionName' => null,
            'street' => 'вул. Хрещатик', 'streetRef' => 'street-ref', 'house' => '22', 'flat' => '5', 'addressNote' => "під'їзд 2",
        ])->with($override);
    }

    public static function sender(): SenderParty
    {
        return new SenderParty('00000000-0000-0000-0000-000000000001', '00000000-0000-0000-0000-000000000010', 'Відправник Тестовий Тестович', '380670000000', 5524, '74/1');
    }

    public static function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__ . '/fixtures/waybill/' . $name . '.json'), true);
    }
}
