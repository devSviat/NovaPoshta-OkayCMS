<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\NovaPoshta\Waybills\InvoicePaymentData;
use PHPUnit\Framework\TestCase;

class InvoicePaymentDataTest extends TestCase
{
    private static function payment(string $module = 'Sviat/InvoicePayment'): object
    {
        return (object)['id' => 7, 'module' => $module];
    }

    public function testNoModuleGivesNothing(): void
    {
        $data = new InvoicePaymentData($this->createStub(EntityFactory::class), 'Okay\\Modules\\Sviat\\Missing\\Entities\\MissingEntity');

        self::assertNull($data->forOrder((object)['id' => 990061, 'payment_method_id' => 7]));
    }

    public function testOtherPaymentGivesNothing(): void
    {
        self::assertNull(InvoicePaymentData::read(self::payment('Sviat/LiqPay'), (object)['edrpou' => '12345678', 'company_name' => 'ТОВ «Приклад»']));
        self::assertNull(InvoicePaymentData::read(null, (object)['edrpou' => '12345678', 'company_name' => 'ТОВ «Приклад»']));
    }

    public function testEmptyFieldsGiveNothing(): void
    {
        self::assertNull(InvoicePaymentData::read(self::payment(), null));
        self::assertNull(InvoicePaymentData::read(self::payment(), (object)['edrpou' => ' ', 'company_name' => 'ТОВ «Приклад»']));
        self::assertNull(InvoicePaymentData::read(self::payment(), (object)['edrpou' => '12345678', 'company_name' => '']));
    }

    public function testInvoiceGivesData(): void
    {
        self::assertSame(['edrpou' => '12345678', 'company' => 'ТОВ «Приклад»'], InvoicePaymentData::read(self::payment(), (object)['edrpou' => ' 12345678 ', 'company_name' => ' ТОВ «Приклад» ']));
    }

    /**
     * Його файли можуть лишитися без його таблиці: замовлення з іншою оплатою не робить до неї запиту (жодної
     * SQL-помилки в лозі).
     */
    public function testOtherPaymentDoesNotQueryInvoiceTable(): void
    {
        $asked = [];
        $payments = $this->createStub(\Okay\Entities\PaymentsEntity::class);
        $payments->method('get')->willReturn(self::payment('Sviat/LiqPay'));
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(function (string $class) use ($payments, &$asked) {
            $asked[] = $class;

            return $class === \Okay\Entities\PaymentsEntity::class ? $payments : $this->createStub(\Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity::class);
        });
        $data = new InvoicePaymentData($factory, \Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity::class);

        self::assertNull($data->forOrder((object)['id' => 990101, 'payment_method_id' => 7]));
        self::assertSame([\Okay\Entities\PaymentsEntity::class], $asked);
    }
}
