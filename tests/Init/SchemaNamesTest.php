<?php

namespace Modules\Sviat\NovaPoshta\Init;

use Okay\Modules\Sviat\NovaPoshta\Entities\GroupEntity;
use Okay\Modules\Sviat\NovaPoshta\Init\Init;
use PHPUnit\Framework\TestCase;

/**
 * The module's tables carry the module's prefix, like its settings: next to the old
 * NovaPoshtaTracking's `sviat__novaposhta_tracking` a single underscore reads as one family.
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
