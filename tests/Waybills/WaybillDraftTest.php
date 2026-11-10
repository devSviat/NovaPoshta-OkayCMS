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

    private static function build(?object $order = null, ?object $snapshot = null, object|false|null $division = false, ?CodKind $cod = null, float $rate = 1.0, ?array $purchases = null, ?array $invoice = null, ?WaybillConfig $config = null, bool $promisedFree = false, ?\Closure $amountDue = null): WaybillDraft|string
    {
        return WaybillDrafts::build(
            $order ?? self::order(),
            $purchases ?? self::purchases(),
            $snapshot ?? self::points(),
            $division === false ? self::division() : $division,
            $config ?? new WaybillConfig(null, null),
            new ShippingConfig(5524, 1.0, 0.001, false),
            $cod,
            $rate,
            new \DateTimeImmutable('2026-11-07 10:00'),
            $amountDue,
            $invoice,
            $promisedFree,
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
        self::assertSame('Замовлення №990061', $draft->description);
        self::assertSame('Parcel', $draft->cargoType);
    }

    public function testDeclaredCostExcludesDelivery(): void
    {
        self::assertSame(2359.0, self::build()->declaredCost);
        self::assertSame(2359.0, self::build(self::order(['separate_delivery' => 1, 'total_price' => 2359.0]))->declaredCost);
    }

    public function testCodOnForCodPaymentUnlessPaid(): void
    {
        $draft = self::build(cod: CodKind::Money);
        self::assertTrue($draft->cod);
        self::assertSame(2454.0, $draft->codAmount);
        self::assertSame(CodKind::Money, $draft->codKind);
        self::assertSame('Recipient', $draft->codPayer);

        self::assertFalse(self::build(self::order(['paid' => 1]), cod: CodKind::Money)->cod);
        self::assertFalse(self::build()->cod);
    }

    public function testKindComesFromPayment(): void
    {
        $draft = self::build(cod: CodKind::Control);
        self::assertTrue($draft->cod);
        self::assertSame(CodKind::Control, $draft->codKind);

        self::assertFalse(self::build(cod: null)->cod);
    }

    public function testPaidOrderHasNoCodEvenWithKind(): void
    {
        self::assertFalse(self::build(self::order(['paid' => 1]), cod: CodKind::Control)->cod);
    }

    public function testCodAmountIsWholeHryvnias(): void
    {
        $draft = self::build(self::order(['total_price' => 1234.56]), cod: CodKind::Money);

        self::assertSame(1235.0, $draft->codAmount);
        self::assertSame(1234.0, $draft->withInput(['cod' => '1', 'cod_amount' => '1234.4'])->codAmount);
    }

    public function testAmountsInUah(): void
    {
        $draft = self::build(self::order(['total_price' => 50.0, 'delivery_price' => 0.0]), cod: CodKind::Money, rate: 40.0);

        self::assertSame(2000.0, $draft->codAmount);
        self::assertSame(2000.0, $draft->declaredCost);
    }

    public function testWeightFromProductsWithDefaults(): void
    {
        $draft = self::build();

        self::assertSame(4.0, $draft->weightKg);
        self::assertEqualsWithDelta(0.003, $draft->volumeM3, 1e-12, 'three units without volume, 0.001 m³ each');
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

    /**
     * Без платного підйому на поверх НП не має поля поверху: кур'єр читає його в примітці (live 09.11.2026: поверх
     * загубився).
     */
    public function testFloorGoesToTheNoteWithoutLifting(): void
    {
        $address = (object)[
            'mode' => 'address', 'settlement_id' => 118064, 'settlement_name' => 'місто Київ', 'area_name' => 'Київська область', 'district_name' => null,
            'street_name' => 'вул. Хрещатик', 'street_ref' => 'ref-street', 'house' => '24', 'flat' => '7',
            'entrance' => '2', 'address_note' => 'Домофон 7', 'floor' => 3, 'elevator' => 0, 'lifting' => 0,
        ];

        self::assertSame("під'їзд 2, поверх 3, Домофон 7", self::build(snapshot: $address, division: null)->addressNote);
        self::assertSame("під'їзд 2, Домофон 7", self::build(snapshot: (object)(['lifting' => 1] + (array)$address), division: null)->addressNote, 'with lifting NP takes the floor itself');
    }

    public function testDraftWithoutPointIsRefused(): void
    {
        self::assertSame('waybill_no_point', self::build(snapshot: self::points(['division_id' => null]), division: null));
        self::assertSame('waybill_point_gone', self::build(division: null));
        self::assertSame('waybill_no_address', self::build(snapshot: (object)['mode' => 'address', 'settlement_name' => 'місто Київ', 'street_name' => '', 'house' => ''], division: null));
    }

    public function testLockerSeatStartsAsACubeOfTheVolume(): void
    {
        $locker = self::build(division: (object)(['category' => 'Postomat'] + (array)self::division()));

        self::assertTrue($locker->locker);
        self::assertSame([14, 14, 14], $locker->seat(), 'cube of 0.003 m³: three units without volume');
        self::assertSame([30, 20, 10], $locker->with(['seatCm' => [30, 20, 10]])->seat());
        self::assertFalse(self::build()->locker);
    }

    /** Обмеження комірки поштомата, які перевіряв старий модуль: 60 × 40 × 30 cm і 20 kg. */
    public function testLockerSeatInput(): void
    {
        $locker = self::build(division: (object)(['category' => 'Postomat'] + (array)self::division()));

        self::assertSame([55, 35, 25], $locker->withInput(['seat_length' => '55', 'seat_width' => '35', 'seat_height' => '25'])->seatCm);
        $errors = $locker->withInput(['seat_length' => '61', 'seat_width' => '0', 'weight' => '21']);
        self::assertIsArray($errors);
        self::assertSame([['field' => 'weight', 'key' => 'waybill_error_locker_weight'], ['field' => 'seat_length', 'key' => 'waybill_error_locker_seat'], ['field' => 'seat_width', 'key' => 'waybill_error_locker_seat']], $errors);
        self::assertInstanceOf(WaybillDraft::class, self::build()->withInput(['seat_length' => '99', 'weight' => '21']), 'a branch has no seat fields');
    }

    public function testInputValidation(): void
    {
        $errors = self::build()->withInput(['weight' => '0', 'phone' => '12', 'cod' => '1', 'cod_amount' => '-1', 'recipient_name' => ' ']);

        self::assertIsArray($errors);
        $fields = array_column($errors, 'field');
        sort($fields);
        self::assertSame(['cod_amount', 'phone', 'recipient_name', 'weight'], $fields);
    }

    /** НП приймає грошовий переказ у цілих гривнях (1234.50 → 1235, перевірено 08.11.2026): 0.4 стало б 0. */
    public function testCodAmountRoundingToNothingIsRefused(): void
    {
        $errors = self::build(cod: CodKind::Money)->withInput(['cod' => '1', 'cod_amount' => '0.4']);

        self::assertIsArray($errors);
        self::assertSame(['cod_amount'], array_column($errors, 'field'));
        self::assertSame(1235.0, self::build(cod: CodKind::Money)->withInput(['cod' => '1', 'cod_amount' => '1234.5'])->codAmount);
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
        self::assertFalse(self::build(cod: CodKind::Money)->withInput([])->cod);
    }

    public function testInvoiceMakesOrganization(): void
    {
        $draft = self::build(invoice: ['edrpou' => '1234 5678', 'company' => 'ТОВ «Приклад»']);

        self::assertSame(['Organization', '12345678', 'ТОВ «Приклад»', true, 'Петренко Іван'], [$draft->recipientType, $draft->edrpou, $draft->companyName, $draft->fromInvoice, $draft->recipientName]);
        self::assertSame(['PrivatePerson', null, null, false], [self::build()->recipientType, self::build()->edrpou, self::build()->companyName, self::build()->fromInvoice]);
    }

    public function testOrganizationNeedsEdrpou(): void
    {
        $org = static fn(string $edrpou): WaybillDraft|array => self::build()->withInput(['recipient_type' => 'Organization', 'edrpou' => $edrpou, 'company_name' => 'ТОВ «Приклад»']);

        self::assertSame([['field' => 'edrpou', 'key' => 'waybill_error_edrpou']], $org('123456'));
        self::assertSame([['field' => 'edrpou', 'key' => 'waybill_error_edrpou']], $org('123456789'));
        self::assertSame('12345678', $org('1234 5678')->edrpou);
        self::assertSame('1234567890', $org('1234567890')->edrpou);
        self::assertSame(['Organization', 'ТОВ «Приклад»'], [$org('12345678')->recipientType, $org('12345678')->companyName]);
    }

    public function testCompanyNameIsOptional(): void
    {
        $draft = self::build()->withInput(['recipient_type' => 'Organization', 'edrpou' => '12345678', 'company_name' => '  ']);

        self::assertInstanceOf(WaybillDraft::class, $draft);
        self::assertNull($draft->companyName);
    }

    public function testPrivatePersonDropsOrganization(): void
    {
        $draft = self::build(invoice: ['edrpou' => '12345678', 'company' => 'ТОВ «Приклад»'])->withInput(['recipient_type' => 'PrivatePerson', 'edrpou' => '12345678']);

        self::assertSame(['PrivatePerson', null, null], [$draft->recipientType, $draft->edrpou, $draft->companyName]);
        self::assertSame([['field' => 'recipient_type', 'key' => 'waybill_error_choice']], self::build()->withInput(['recipient_type' => 'Company']));
    }

    public function testOrganizationIssue(): void
    {
        $draft = self::build(invoice: ['edrpou' => '123456789', 'company' => 'ТОВ «Приклад»']);

        self::assertSame('waybill_error_edrpou', $draft->organizationIssue());
        self::assertNull(self::build(invoice: ['edrpou' => '12345678', 'company' => 'ТОВ «Приклад»'])->organizationIssue());
        self::assertNull(self::build()->organizationIssue());
    }

    /** Доставка входить у суму замовлення: магазин уже її взяв, тож НП не повинна знову стягувати її з отримувача. */
    public function testAutoPayerIsTheSenderWhenDeliveryIsInTheTotal(): void
    {
        $draft = self::build(cod: CodKind::Money);

        self::assertSame('Sender', $draft->payerType);
        self::assertSame(95.0, $draft->deliveryInTotal);
    }

    public function testAutoPayerIsTheRecipientWhenDeliveryIsPaidApart(): void
    {
        $draft = self::build(self::order(['separate_delivery' => 1, 'total_price' => 2359.0]));

        self::assertSame('Recipient', $draft->payerType);
        self::assertSame(0.0, $draft->deliveryInTotal);
        self::assertSame('Recipient', self::build(self::order(['delivery_price' => 0]))->payerType, 'unpriced: the manager settles delivery with the customer');
    }

    /** Кошик сказав "безкоштовно": НП не повинна стягувати з покупця при отриманні. */
    public function testAutoPayerIsTheSenderWhenTheShopPromisedFreeDelivery(): void
    {
        $order = self::order(['delivery_price' => 0, 'total_price' => 1200.0]);

        self::assertSame('Sender', self::build($order, promisedFree: true)->payerType);
        self::assertSame('free', self::build($order, promisedFree: true)->senderPaysBecause);
    }

    /** Налаштування — лише те, з чого стартує форма: заданий «Recipient» ніколи не стягує за доставку двічі. */
    public function testSenderPaysWhateverTheSettingWhenTheShopAlreadyTookDelivery(): void
    {
        $recipient = new WaybillConfig(null, null, payerType: 'Recipient');

        self::assertSame('Sender', self::build(config: $recipient)->payerType);
        self::assertSame('in_total', self::build(config: $recipient)->senderPaysBecause);
        self::assertSame('Recipient', self::build(self::order(['separate_delivery' => 1]), config: $recipient)->payerType);
        self::assertNull(self::build(self::order(['separate_delivery' => 1]), config: $recipient)->senderPaysBecause);
        self::assertSame('Sender', self::build(self::order(['separate_delivery' => 1]), config: new WaybillConfig(null, null, payerType: 'Sender'))->payerType);
    }

    public function testDescriptionIsTheOrderNumberOrTheShopText(): void
    {
        self::assertSame('Замовлення №990061', self::build()->description);
        self::assertSame('Запчастини', self::build(config: new WaybillConfig(null, null, descriptionMode: 'text', description: 'Запчастини'))->description);
    }

    /** Як у старому модулі: артикули (назва, де артикула немає), «(2 шт)» для кількох, обрізано до 100 символів НП. */
    public function testExtraInfoListsTheGoods(): void
    {
        $purchases = [
            (object)['amount' => 2, 'sku' => 'ZR879A01', 'product_name' => 'Фільтр', 'variant' => (object)['weight' => 1, \Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume::FIELD => null]],
            (object)['amount' => 1, 'sku' => '', 'product_name' => 'Щітка', 'variant' => (object)['weight' => 1, \Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume::FIELD => null]],
        ];

        self::assertSame('ZR879A01 (2 шт), Щітка', self::build(purchases: $purchases)->extraInfo);
        self::assertSame('Фільтр (2 шт), Щітка', self::build(purchases: $purchases, config: new WaybillConfig(null, null, extraInfo: 'names'))->extraInfo);
        self::assertSame('', self::build(purchases: $purchases, config: new WaybillConfig(null, null, extraInfo: 'none'))->extraInfo);
        $many = array_fill(0, 30, (object)['amount' => 1, 'sku' => 'ABCDEFGH', 'product_name' => 'x', 'variant' => (object)['weight' => 1, \Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume::FIELD => null]]);
        self::assertSame(100, mb_strlen(self::build(purchases: [$many[0], ...array_map(static fn(int $i): object => (object)['amount' => 1, 'sku' => 'SKU' . $i, 'product_name' => 'x', 'variant' => $many[0]->variant], range(10, 60))])->extraInfo));
    }

    public function testExtraInfoInput(): void
    {
        self::assertSame('Крихке', self::build()->withInput(['extra_info' => ' Крихке '])->extraInfo);
        self::assertSame([['field' => 'extra_info', 'key' => 'waybill_error_extra_info']], self::build()->withInput(['extra_info' => str_repeat('я', 101)]));
    }

    /**
     * Інший модуль (передоплата) зменшує суму, яку збирає НП; якщо лишилося нуль або менше, післяплати немає взагалі
     * (аудит: «RedeliveryString '0'/'-10'»).
     */
    public function testNothingLeftToCollectTurnsCodOff(): void
    {
        foreach ([0.0, -10.0] as $due) {
            $draft = self::build(cod: CodKind::Money, amountDue: static fn(float $total): float => $due);

            self::assertFalse($draft->cod, (string)$due);
            self::assertSame(0.0, $draft->codAmount, (string)$due);
        }
        $draft = self::build(cod: CodKind::Money, amountDue: static fn(float $total): float => $total - 100);
        self::assertTrue($draft->cod);
        self::assertSame(round((float)self::order()->total_price - 100), $draft->codAmount);
    }

    /**
     * НП приймає до 60 символів назви й відхиляє обрізане слово як скорочення (live 09.11.2026); у масовому запуску
     * форми немає.
     */
    public function testLongNameIsCutAtAWordForNovaPoshta(): void
    {
        $draft = self::build(self::order(['last_name' => 'Довгоприходько-Святополк-Мирська', 'name' => 'Олександра-Вікторія Іванівна']));

        self::assertSame('Довгоприходько-Святополк-Мирська Олександра-Вікторія', $draft->recipientName);
        self::assertInstanceOf(WaybillDraft::class, $draft->withInput([]), 'the cut name passes the form check');
        self::assertSame(60, mb_strlen(self::build(self::order(['last_name' => str_repeat('я', 70), 'name' => '']))->recipientName), 'one endless word: cut at the limit');
    }

    /**
     * Онлайн-оплату ще не здійснено: НП нічого не збирає при отриманні. Замовлення з післяплатою або вже оплачене не
     * чекає.
     */
    public function testDraftKnowsTheOrderAwaitsPayment(): void
    {
        self::assertTrue(self::build(self::order(['paid' => 0]))->awaitsPayment);
        self::assertFalse(self::build(self::order(['paid' => 1]))->awaitsPayment);
        self::assertFalse(self::build(self::order(['paid' => 0]), cod: CodKind::Money)->awaitsPayment);
        self::assertFalse(self::build(self::order(['paid' => 0]), cod: CodKind::Money, amountDue: static fn(float $total): float => 0.0)->awaitsPayment, 'prepaid in full by another module');
    }

    /** НП не приймає оголошену цінність, меншу за післяплату, і мовчки піднімає її (жива перевірка 09.11.2026: 1799 → 1958). */
    public function testDeclaredValueIsNotBelowWhatNpCollects(): void
    {
        $inTotal = self::build(cod: CodKind::Money);
        self::assertSame(2454.0, $inTotal->codAmount, 'товари й доставка в сумі замовлення');
        self::assertSame(2454.0, $inTotal->declaredCost);

        $apart = self::build(self::order(['separate_delivery' => 1, 'total_price' => 2359.0]), cod: CodKind::Money);
        self::assertSame(2359.0, $apart->codAmount, 'доставка окремо: лише товари');
        self::assertSame(2359.0, $apart->declaredCost);

        self::assertSame(2359.0, self::build()->declaredCost, 'без післяплати — вартість товарів');
        self::assertSame(3000.0, self::build()->withInput(['cod' => '1', 'cod_amount' => '3000', 'declared_cost' => '2359'])->declaredCost);
        self::assertSame(2500.0, self::build()->withInput(['cod' => '1', 'cod_amount' => '1000', 'declared_cost' => '2500'])->declaredCost, 'більша цінність лишається');
    }

    /** Форма пропонує типи, увімкнені в налаштуваннях, і завжди власний тип чернетки. */
    public function testFormTakesTheTypesTurnedOn(): void
    {
        $draft = self::build(config: new WaybillConfig(null, null, cargoTypes: ['Parcel', 'Documents']));

        self::assertSame(['Parcel', 'Documents'], $draft->cargoTypes);
        self::assertSame([['field' => 'cargo_type', 'key' => 'waybill_error_choice']], $draft->withInput(['cargo_type' => 'Cargo']));
        self::assertSame('Documents', $draft->withInput(['cargo_type' => 'Documents', 'weight' => '1'])->cargoType);
        $own = self::build(config: new WaybillConfig(null, null, cargoType: 'Cargo', cargoTypes: ['Parcel']));
        self::assertSame('Cargo', $own->withInput(['cargo_type' => 'Cargo'])->cargoType, 'a type turned off later still fits a draft that has it');
    }

    /** НП відхиляє документи понад 1 kg (live 09.11.2026: «Weight is not allowed for Documents»). */
    public function testDocumentsWeighOneKilogramAtMost(): void
    {
        self::assertSame([['field' => 'weight', 'key' => 'waybill_error_documents_weight']], self::build()->withInput(['cargo_type' => 'Documents', 'weight' => '1.5']));
        self::assertSame(1.0, self::build()->withInput(['cargo_type' => 'Documents', 'weight' => '1'])->weightKg);
        self::assertSame(1.5, self::build()->withInput(['cargo_type' => 'Parcel', 'weight' => '1.5'])->weightKg);
    }

    /** НП не приймає «Вантаж» до поштомата (api-docs-ua «ЕН на поштомат»: Parcel, Documents). */
    public function testLockerTakesNoCargo(): void
    {
        $locker = self::build(division: (object)(['category' => 'Postomat'] + (array)self::division()), config: new WaybillConfig(null, null, cargoType: 'Cargo'));

        self::assertSame('Parcel', $locker->cargoType);
        self::assertSame([['field' => 'cargo_type', 'key' => 'waybill_error_locker_cargo']], $locker->withInput(['cargo_type' => 'Cargo']));
        self::assertSame('Cargo', self::build(config: new WaybillConfig(null, null, cargoType: 'Cargo'))->cargoType);
    }

    public function testPromisedFreeDelivery(): void
    {
        $order = self::order(['delivery_price' => 0, 'total_price' => 1200.0]);
        $paid = (object)['paid' => 1, 'free_from' => 1000, 'price' => 70];

        self::assertTrue(WaybillDrafts::promisedFree($paid, $order, (object)['price_source' => 'np']), 'past the threshold');
        self::assertTrue(WaybillDrafts::promisedFree((object)['paid' => 0, 'free_from' => 0, 'price' => 0], $order, null), 'a free method');
        self::assertTrue(WaybillDrafts::promisedFree((object)['paid' => 1, 'free_from' => 5000, 'price' => 70], $order, (object)['price_source' => 'external']), 'another module (a promo) made it free');
        self::assertFalse(WaybillDrafts::promisedFree((object)['paid' => 1, 'free_from' => 5000, 'price' => 70], $order, (object)['price_source' => 'fallback']), 'NP did not price it');
        self::assertFalse(WaybillDrafts::promisedFree((object)['paid' => 1, 'free_from' => 5000, 'price' => 70], self::order(), (object)['price_source' => 'np']), 'priced, below the threshold');
        self::assertFalse(WaybillDrafts::promisedFree(null, $order, null));
    }
}
