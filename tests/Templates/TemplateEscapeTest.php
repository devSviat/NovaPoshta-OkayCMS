<?php

namespace Modules\Sviat\NovaPoshta\Templates;

use Okay\Modules\Sviat\NovaPoshta\Init\Init;
use PHPUnit\Framework\TestCase;

/**
 * Шаблони адмінки й теми не мають autoescape: кожне виведене значення потребує |escape. Без нього дозволено:
 * capture (його частини екрановано там, де їх захоплено) і |convert (число, відформатоване ядром).
 */
class TemplateEscapeTest extends TestCase
{
    public function testEveryPrintedValueIsEscaped(): void
    {
        $root = dirname((string)new \ReflectionClass(Init::class)->getFileName(), 2);
        $files = [...glob($root . '/Backend/design/html/*.tpl'), ...glob($root . '/design/html/*.tpl')];
        self::assertNotEmpty($files);
        $raw = [];
        foreach ($files as $file) {
            foreach (file($file) as $n => $line) {
                preg_match_all('/\{\$[^}]*\}/', $line, $m);
                foreach ($m[0] as $tag) {
                    if (str_contains($tag, '|escape') || preg_match('/^\{\$[\w.>-]+\s*=/', $tag) || str_starts_with($tag, '{$smarty.capture.') || str_contains($tag, '|convert')) {
                        continue;
                    }
                    $raw[] = basename(dirname($file, 2)) . '/' . basename($file) . ':' . ($n + 1) . ' ' . $tag;
                }
            }
        }

        self::assertSame([], $raw);
    }
}
