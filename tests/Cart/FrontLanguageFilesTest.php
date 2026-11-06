<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Extenders\FrontExtender;
use PHPUnit\Framework\TestCase;

class FrontLanguageFilesTest extends TestCase
{
    private static function dir(): string
    {
        return dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/design';
    }

    /** @return array<string, string> */
    private static function load(string $label): array
    {
        $lang = [];
        include self::dir() . "/lang/{$label}.php";

        return $lang;
    }

    public function testAllLanguagesHaveTheSameKeys(): void
    {
        $ua = array_keys(self::load('ua'));
        sort($ua);
        foreach (['en', 'ru'] as $label) {
            $keys = array_keys(self::load($label));
            sort($keys);
            self::assertSame($ua, $keys, $label);
        }
    }

    public function testTemplateAndScriptTextsExist(): void
    {
        $ua = self::load('ua');
        preg_match_all('/\$lang->(sviat__novaposhta__\w+)/', (string)file_get_contents(self::dir() . '/html/cart_delivery.tpl'), $m);
        $used = $m[1];
        foreach ((new \ReflectionClassConstant(FrontExtender::class, 'SCRIPT_TEXTS'))->getValue() as $key) {
            $used[] = 'sviat__novaposhta__' . $key;
        }

        self::assertSame([], array_values(array_diff(array_unique($used), array_keys($ua))));
    }

    public function testScriptReadsOnlyTextsItIsGiven(): void
    {
        preg_match_all('/texts\.(cart_\w+)/', (string)file_get_contents(self::dir() . '/js/novaposhta_cart.js'), $m);
        $given = (new \ReflectionClassConstant(FrontExtender::class, 'SCRIPT_TEXTS'))->getValue();

        self::assertSame([], array_values(array_diff(array_unique($m[1]), $given)));
    }
}
