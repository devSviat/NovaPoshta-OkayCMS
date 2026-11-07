<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;
use Okay\Modules\Sviat\NovaPoshta\Quote\ShippingConfig;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillConfig;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillDraft;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillDrafts;
use PHPUnit\Framework\TestCase;

class WaybillDraftTest extends TestCase
{
    private static function order(array $override = []): object
    {
        return (object)($override + [
            'id' => 990061, 'name' => 'Іван', 'last_name' => 'Петренко', 'phone' => '+38 (050) 000-00-00',
            'total_price' => 2454.0, 'delivery_price' => 95.0, 'separate_delivery' => 0, 'paid' => 0,
        ]);
    }

    private static function purchases(): array
    {
        return [
            (object)['amount' => 2, 'variant' => (object)['weight' => 1.5, \Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume::FIELD => null]],
            (object)['amount' => 1, 'variant' => (object)['weight' => 0, \Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume::FIELD => null]],
        ];
    }

    private static function points(array $override = []): object
    {
        return (object)($override + [
            'mode' => 'points', 'settlement_name' => 'місто Київ', 'area_name' => 'Київська область', 'district_name' => null,
            'division_id' => 7162, 'division_number' => '11/1',
        ]);
    }

    private static function division(): object
    {
        return (object)['id' => 7162, 'number' => '11/1', 'name_uk' => 'Відділення №1: вул. Пирогівський шлях, 135', 'settlement_id' => 118064];
    }

    private static function build(?object $order = null, ?object $snapshot = null, object|false|null $division = false, bool $cod = false, float $rate = 1.0, ?array $purchases = null): WaybillDraft|string
    {
        return WaybillDrafts::build(
            $order ?? self::order(),
            $purchases ?? self::purchases(),
            $snapshot ?? self::points(),
            $division === false ? self::division() : $division,
            new WaybillConfig(null, null),
            new ShippingConfig(5524, 1.0, 0.001, CodKind::Money, false),
            $cod,
            $rate,
            new \DateTimeImmutable('2026-11-07 10:00'),
        );
    }

    public function testRecipientAndPointComeFromTheOrder(): void
    {
        $draft = self::build();

        self::assertSame(DeliveryMode::Points, $draft->mode);
        self::assertSame('Петренко Іван', $draft->recipientName);
        self::assertSame('380500000000', $draft->recipientPhone);
        self::assertSame(7162, $draft->divisionId);
        self::assertSame('11/1', $draft->divisionNumber);
        self::assertSame('Відділення №1: вул. Пирогівський шлях, 135', $draft->divisionName);
        self::assertSame('07.11.2026', $draft->shipDate);
        self::assertSame('Товари інтернет-магазину', $draft->description);
        self::assertSame('Parcel', $draft->cargoType);
    }

    public function testDeclaredCostExcludesDelivery(): void
    {
        self::assertSame(2359.0, self::build()->declaredCost);
        self::assertSame(2359.0, self::build(self::order(['separate_delivery' => 1, 'total_price' => 2359.0]))->declaredCost);
    }

    public function testCodOnForCodPaymentUnlessPaid(): void
    {
        $draft = self::build(cod: true);
        self::assertTrue($draft->cod);
        self::assertSame(2454.0, $draft->codAmount);
        self::assertSame(CodKind::Money, $draft->codKind);
        self::assertSame('Recipient', $draft->codPayer);

        self::assertFalse(self::build(self::order(['paid' => 1]), cod: true)->cod);
        self::assertFalse(self::build()->cod);
    }

    public function testAmountsInUah(): void
    {
        $draft = self::build(self::order(['total_price' => 50.0, 'delivery_price' => 0.0]), cod: true, rate: 40.0);

        self::assertSame(2000.0, $draft->codAmount);
        self::assertSame(2000.0, $draft->declaredCost);
    }

    public function testWeightFromProductsWithDefaults(): void
    {
        $draft = self::build();

        self::assertSame(4.0, $draft->weightKg);
        self::assertSame(0.001, $draft->volumeM3);
    }

    public function testAddressNoteJoinsEntrance(): void
    {
        $address = (object)[
            'mode' => 'address', 'settlement_id' => 118064, 'settlement_name' => 'місто Київ', 'area_name' => 'Київська область', 'district_name' => null,
            'street_name' => 'вул. Хрещатик', 'street_ref' => 'ref-street', 'house' => '22', 'flat' => '5',
            'entrance' => '2', 'address_note' => 'код 15', 'floor' => 5, 'elevator' => 1, 'lifting' => 1,
        ];
        $draft = self::build(snapshot: $address, division: null);

        self::assertSame(DeliveryMode::Address, $draft->mode);
        self::assertSame("під'їзд 2, код 15", $draft->addressNote);
        self::assertTrue($draft->lifting);
        self::assertSame(5, $draft->floor);
        self::assertTrue($draft->elevator);

        $long = self::build(snapshot: (object)(['address_note' => str_repeat('я', 60)] + (array)$address), division: null);
        self::assertSame(50, mb_strlen($long->addressNote));
    }

    public function testDraftWithoutPointIsRefused(): void
    {
        self::assertSame('waybill_no_point', self::build(snapshot: self::points(['division_id' => null]), division: null));
        self::assertSame('waybill_point_gone', self::build(division: null));
        self::assertSame('waybill_no_address', self::build(snapshot: (object)['mode' => 'address', 'settlement_name' => 'місто Київ', 'street_name' => '', 'house' => ''], division: null));
    }

    public function testInputValidation(): void
    {
        $errors = self::build()->withInput(['weight' => '0', 'phone' => '12', 'cod' => '1', 'cod_amount' => '-1', 'recipient_name' => ' ']);

        self::assertIsArray($errors);
        $fields = array_column($errors, 'field');
        sort($fields);
        self::assertSame(['cod_amount', 'phone', 'recipient_name', 'weight'], $fields);
    }

    public function testInputOverridesTheDraft(): void
    {
        $draft = self::build()->withInput([
            'recipient_name' => 'Сидоренко Олена', 'phone' => '0670000001', 'weight' => '2,5', 'volume' => '0.02', 'declared_cost' => '1000',
            'description' => 'Одяг', 'cargo_type' => 'Cargo', 'payer_type' => 'Sender', 'payment_method' => 'NonCash',
            'cod' => '1', 'cod_amount' => '1200', 'cod_kind' => 'control', 'cod_payer' => 'Sender',
        ]);

        self::assertInstanceOf(WaybillDraft::class, $draft);
        self::assertSame(['Сидоренко Олена', '380670000001', 2.5, 0.02, 1000.0, 'Одяг', 'Cargo', 'Sender', 'NonCash', true, 1200.0, CodKind::Control, 'Sender'], [
            $draft->recipientName, $draft->recipientPhone, $draft->weightKg, $draft->volumeM3, $draft->declaredCost, $draft->description,
            $draft->cargoType, $draft->payerType, $draft->paymentMethod, $draft->cod, $draft->codAmount, $draft->codKind, $draft->codPayer,
        ]);
        self::assertFalse(self::build(cod: true)->withInput([])->cod);
    }
}
