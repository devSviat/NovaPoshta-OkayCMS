<?php

namespace Modules\Sviat\NovaPoshta\Templates;

use Okay\Modules\Sviat\NovaPoshta\Init\Init;
use PHPUnit\Framework\TestCase;

/**
 * Admin and theme templates have no autoescape: every printed value needs |escape. Allowed without it:
 * a capture (its parts are escaped where captured) and |convert (a number formatted by the core).
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
