<?php

namespace Modules\Sviat\NovaPoshta\Templates;

use PHPUnit\Framework\TestCase;

/** Передплачене замовлення теж оплачується двічі: попередження залежить лише від платника, а не від післяплати. */
class DoubleDeliveryWarningTest extends TestCase
{
    public function testWarningIgnoresCashOnDelivery(): void
    {
        $base = dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/Backend/design/';
        preg_match('/<div[^>]*np-wb__double[^>]*>/', (string)file_get_contents($base . 'html/waybill.tpl'), $tag);
        preg_match('/np-wb__double[\s\S]{0,600}?warning\.hidden = [^;]+;/', (string)file_get_contents($base . 'js/waybill.js'), $js);

        self::assertNotEmpty($tag);
        self::assertStringNotContainsString('cod', $tag[0]);
        self::assertNotEmpty($js);
        self::assertStringNotContainsString('cod', $js[0]);
    }
}
