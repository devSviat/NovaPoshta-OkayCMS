<?php

namespace Modules\Sviat\NovaPoshta\Templates;

use PHPUnit\Framework\TestCase;

/** Пояснення платника видно без наведення: у налаштуваннях під полем, у формі — поки вибрано «Відправник». */
class PayerExplanationTest extends TestCase
{
    private static function file(string $path): string
    {
        return (string)file_get_contents(dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/Backend/design/' . $path);
    }

    public function testSettingsNameTheFieldADefaultAndShowWhy(): void
    {
        $settings = self::file('html/settings.tpl');
        preg_match('~<label[^>]*for="np_waybill_payer"[^>]*>(.*?)</label>\s*<select id="np_waybill_payer"[^>]*aria-describedby="np_waybill_payer_help"~s', $settings, $field);
        preg_match('~<select id="np_waybill_payment".*?</div>\s*<div class="[^"]*np_field_help[^"]*" id="np_waybill_payer_help">(.*?)</div>~s', $settings, $help);

        self::assertNotEmpty($field);
        self::assertSame('{$btr->sviat__novaposhta__waybill_payer_default|escape}', $field[1]);
        self::assertNotEmpty($help, 'its own grid row right under the payer and payment selects');
        self::assertSame('{$btr->sviat__novaposhta__waybill_payer_tip|escape}', $help[1]);
        self::assertStringNotContainsString('data-hint="{$btr->sviat__novaposhta__waybill_payer_tip', $settings, 'no longer only in a tooltip');
    }

    public function testFormHidesTheReasonForAnotherPayer(): void
    {
        preg_match('/np-wb__payer \.np-wb__reason[\s\S]{0,200}?reason\.hidden = ([^;]+);/', self::file('js/waybill.js'), $js);

        self::assertNotEmpty($js);
        self::assertSame("input.value !== 'Sender'", $js[1]);
    }
}
