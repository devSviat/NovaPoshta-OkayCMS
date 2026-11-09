<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillConfig;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillSettings;
use PHPUnit\Framework\TestCase;

class WaybillSettingsTest extends TestCase
{
    public function testEmptyFormGivesDefaults(): void
    {
        $config = WaybillSettings::parse([]);

        self::assertInstanceOf(WaybillConfig::class, $config);
        self::assertNull($config->contactRef);
        self::assertNull($config->senderPhone);
        self::assertSame('16:00', $config->cutoff);
        self::assertSame('Recipient', $config->payerType, 'the drafts switch to the sender where the order total already holds delivery');
        self::assertSame('Cash', $config->paymentMethod);
        self::assertSame('Parcel', $config->cargoType);
        self::assertSame('Recipient', $config->codPayer);
        self::assertSame('Товари інтернет-магазину', $config->description);
        self::assertSame('order', $config->descriptionMode, 'as the old module: «Замовлення №…»');
        self::assertSame('skus', $config->extraInfo, 'as the old module: SKUs of the order');
    }

    /** 'auto' was the default before 1.0.0: it reads as the recipient, the drafts still switch when needed. */
    public function testStoredAutoPayerReadsAsRecipient(): void
    {
        self::assertSame('Recipient', WaybillSettings::parse(['payer_type' => 'auto'])->payerType);
    }

    public function testDescriptionModeAndExtraInfo(): void
    {
        self::assertSame('text', WaybillSettings::parse(['description_mode' => 'text'])->descriptionMode);
        self::assertSame('names', WaybillSettings::parse(['extra_info' => 'names'])->extraInfo);
        self::assertSame('none', WaybillSettings::parse(['extra_info' => 'none'])->extraInfo);
        self::assertSame(['waybill_error_choice'], WaybillSettings::parse(['description_mode' => 'html']));
        self::assertSame(['waybill_error_choice'], WaybillSettings::parse(['extra_info' => 'price']));
    }

    public function testCutoffMustBeATimeOfDay(): void
    {
        self::assertSame(['waybill_error_cutoff'], WaybillSettings::parse(['cutoff' => '25:00']));
        self::assertSame('09:30', WaybillSettings::parse(['cutoff' => '9:30'])->cutoff);
    }

    public function testPhoneIsStoredForNovaPoshta(): void
    {
        self::assertSame('380670000000', WaybillSettings::parse(['sender_phone' => '0670000000'])->senderPhone);
        self::assertSame(['waybill_error_phone'], WaybillSettings::parse(['sender_phone' => '12']));
    }

    public function testChoicesOutsideTheListAreRefused(): void
    {
        self::assertSame(['waybill_error_payer'], WaybillSettings::parse(['payer_type' => 'Third']));
        self::assertSame(['waybill_error_payment'], WaybillSettings::parse(['payment_method' => 'Card']));
        self::assertSame(['waybill_error_cargo'], WaybillSettings::parse(['cargo_type' => 'Pallet']));
        self::assertSame(['waybill_error_cod_payer'], WaybillSettings::parse(['cod_payer' => 'Bank']));
        self::assertSame('Documents', WaybillSettings::parse(['cargo_type' => 'Documents'])->cargoType);
    }

    public function testDescriptionIsLimited(): void
    {
        self::assertSame(['waybill_error_description'], WaybillSettings::parse(['description' => str_repeat('я', 101)]));
        self::assertSame('Одяг', WaybillSettings::parse(['description' => '  Одяг '])->description);
    }

    public function testContactIsARef(): void
    {
        self::assertSame('00000000-0000-0000-0000-000000000010', WaybillSettings::parse(['contact_ref' => '00000000-0000-0000-0000-000000000010'])->contactRef);
        self::assertSame(['waybill_error_contact'], WaybillSettings::parse(['contact_ref' => '<script>']));
    }
}
