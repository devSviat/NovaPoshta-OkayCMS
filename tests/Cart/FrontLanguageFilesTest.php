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
        $templates = glob(self::dir() . '/html/*.tpl');
        self::assertContains('order_delivery.tpl', array_map('basename', $templates));
        preg_match_all('/\$lang->(sviat__novaposhta__\w+)/', implode("\n", array_map('file_get_contents', $templates)), $m);
        $used = $m[1];
        foreach ((new \ReflectionClassConstant(FrontExtender::class, 'SCRIPT_TEXTS'))->getValue() as $key) {
            $used[] = 'sviat__novaposhta__' . $key;
        }

        self::assertSame([], array_values(array_diff(array_unique($used), array_keys($ua))));
    }

    public function testEveryValidationErrorHasAText(): void
    {
        $ua = self::load('ua');
        $code = (string)file_get_contents(dirname(self::dir()) . '/Cart/CartSelectionValidator.php') . (string)file_get_contents(dirname(self::dir()) . '/Cart/AddressInput.php');
        preg_match_all("/(?:return |\\\$fail\\()'(error_\\w+)'(?=;|\\))/", $code, $m);
        self::assertContains('error_lifting_floor', $m[1]);
        $keys = array_merge(array_filter($m[1]), ['error_point_too_heavy', 'error_point_too_expensive', 'error_point_too_big']);

        foreach ($keys as $key) {
            self::assertArrayHasKey('sviat__novaposhta__' . $key, $ua);
        }
        self::assertGreaterThanOrEqual(5, count($keys));
    }

    public function testScriptReadsOnlyTextsItIsGiven(): void
    {
        preg_match_all('/texts\.(cart_\w+)/', (string)file_get_contents(self::dir() . '/js/novaposhta_cart.js'), $m);
        $given = (new \ReflectionClassConstant(FrontExtender::class, 'SCRIPT_TEXTS'))->getValue();

        self::assertSame([], array_values(array_diff(array_unique($m[1]), $given)));
    }
}
