<?php

namespace Modules\Sviat\NovaPoshta\Init;

use Okay\Modules\Sviat\NovaPoshta\Entities\GroupEntity;
use Okay\Modules\Sviat\NovaPoshta\Init\Init;
use PHPUnit\Framework\TestCase;

/**
 * Таблиці модуля мають префікс модуля, як і його налаштування: поруч зі старим
 * `sviat__novaposhta_tracking` від NovaPoshtaTracking одинарне підкреслення читається як одна родина.
 */
class SchemaNamesTest extends TestCase
{
    public function testEveryTableHasTheModulePrefix(): void
    {
        foreach (array_keys(Init::tables()) as $entity) {
            self::assertStringStartsWith('__sviat__novaposhta__', $entity::getTable(), $entity);
        }
        self::assertStringStartsWith('__lang_sviat__novaposhta__', GroupEntity::getLangTable());
    }
}
