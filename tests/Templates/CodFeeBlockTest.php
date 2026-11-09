<?php

namespace Modules\Sviat\NovaPoshta\Templates;

use PHPUnit\Framework\TestCase;

class CodFeeBlockTest extends TestCase
{
    /** Themes show a payment's description box whenever front_cart_payment prints anything, a newline included. */
    public function testPrintsNothingOutsideTheLine(): void
    {
        $template = file_get_contents(dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/design/html/cart_cod_fee.tpl');
        $withoutTags = preg_replace('/\{\*.*?\*\}|\{[^}]*\}/s', '', $template);
        $outside = preg_replace('/<span\b[^>]*><\/span>/', '', $withoutTags);

        self::assertSame('', $outside);
    }
}
