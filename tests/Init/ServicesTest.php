<?php

namespace Modules\Sviat\NovaPoshta\Init;

use Okay\Core\OkayContainer\Reference\ServiceReference;
use Okay\Modules\Sviat\NovaPoshta\Extenders\BackendExtender;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderRowsCleanup;
use PHPUnit\Framework\TestCase;

/** The container passes arguments by position: a definition out of step with its constructor fails only at runtime. */
class ServicesTest extends TestCase
{
    /** @return array<string, array{class: string, arguments?: list<mixed>}> */
    private static function services(): array
    {
        return require dirname((new \ReflectionClass(\Okay\Modules\Sviat\NovaPoshta\Init\Init::class))->getFileName()) . '/services.php';
    }

    public function testEveryDefinitionMatchesItsConstructor(): void
    {
        foreach (self::services() as $name => $definition) {
            $constructor = new \ReflectionClass($definition['class'])->getConstructor();
            $params = $constructor?->getParameters() ?? [];
            $arguments = $definition['arguments'] ?? [];
            $required = count(array_filter($params, static fn(\ReflectionParameter $p): bool => !$p->isOptional()));
            self::assertGreaterThanOrEqual($required, count($arguments), $name);
            $variadic = $params !== [] && end($params)->isVariadic();
            if (!$variadic) {
                self::assertLessThanOrEqual(count($params), count($arguments), $name);
            }
            foreach ($arguments as $i => $argument) {
                $type = $params[min($i, count($params) - 1)]->getType();
                if ($argument instanceof ServiceReference && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    self::assertTrue(is_a($argument->getName(), $type->getName(), true), "{$name} #{$i}: {$argument->getName()} is not {$type->getName()}");
                }
            }
        }
    }

    public function testOrderCleanupIsAService(): void
    {
        self::assertArrayHasKey(OrderRowsCleanup::class, self::services());
        $types = array_map(static fn(\ReflectionParameter $p): string => (string)$p->getType(), new \ReflectionClass(BackendExtender::class)->getConstructor()->getParameters());
        self::assertContains(OrderRowsCleanup::class, $types);
    }
}
