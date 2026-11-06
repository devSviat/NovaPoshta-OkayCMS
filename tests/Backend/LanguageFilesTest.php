<?php

namespace Modules\Sviat\NovaPoshta\Backend;

use PHPUnit\Framework\TestCase;

class LanguageFilesTest extends TestCase
{
    /** @return array<string, string> */
    private static function load(string $label): array
    {
        $lang = [];
        include dirname(__DIR__, 5) . "/Okay/Modules/Sviat/NovaPoshta/Backend/lang/{$label}.php";

        return $lang;
    }

    public function testAllAdminLanguagesHaveTheSameKeys(): void
    {
        $ua = array_keys(self::load('ua'));
        sort($ua);
        self::assertNotEmpty($ua);

        foreach (['en', 'ru'] as $label) {
            $keys = array_keys(self::load($label));
            sort($keys);
            self::assertSame($ua, $keys, $label);
        }
    }

    public function testCategoryLabelsExist(): void
    {
        $ua = self::load('ua');
        foreach (\Okay\Modules\Sviat\NovaPoshta\Groups\GroupsView::vars([], [])['categories'] as $category) {
            self::assertArrayHasKey($category['label_key'], $ua);
        }
    }

    public function testEveryKeyUsedInTheTemplateExists(): void
    {
        $template = file_get_contents(dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/Backend/design/html/settings.tpl');
        preg_match_all('/\$btr->(sviat__novaposhta__\w+)/', $template, $m);

        self::assertNotEmpty($m[1]);
        self::assertSame([], array_values(array_diff(array_unique($m[1]), array_keys(self::load('ua')))));
    }
}
