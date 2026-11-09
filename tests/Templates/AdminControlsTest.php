<?php

namespace Modules\Sviat\NovaPoshta\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Чекбокси й радіокнопки адмінки виглядають як решта OkayCMS: прихований input з label ядра
 * (okay_ckeckbox / okay_type_radio) або перемикач ядра — ніколи не нативний елемент браузера.
 */
class AdminControlsTest extends TestCase
{
    public function testNoNativeCheckboxesOrRadios(): void
    {
        $native = [];
        foreach (glob(dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/Backend/design/html/*.tpl') as $file) {
            preg_match_all('/<input\b[^>]*type="(?:checkbox|radio)"[^>]*>/', (string)file_get_contents($file), $m);
            foreach ($m[0] as $tag) {
                if (!preg_match('/class="[^"]*\b(?:hidden_check|switch-input)\b/', $tag)) {
                    $native[] = basename($file) . ': ' . $tag;
                }
            }
        }

        self::assertSame([], $native);
    }
}
