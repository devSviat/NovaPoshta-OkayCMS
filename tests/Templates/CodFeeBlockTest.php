<?php

namespace Modules\Sviat\NovaPoshta\Templates;

use PHPUnit\Framework\TestCase;

class CodFeeBlockTest extends TestCase
{
    /** Теми показують блок опису оплати, щойно front_cart_payment щось виводить, навіть перенесення рядка. */
    public function testPrintsNothingOutsideTheLine(): void
    {
        $template = file_get_contents(dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/design/html/cart_cod_fee.tpl');
        $withoutTags = preg_replace('/\{\*.*?\*\}|\{[^}]*\}/s', '', $template);
        $outside = preg_replace('/<span\b[^>]*><\/span>/', '', $withoutTags);

        self::assertSame('', $outside);
    }
}
