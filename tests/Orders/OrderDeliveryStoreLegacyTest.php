<?php

namespace Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Init\Init;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryStore;
use PHPUnit\Framework\TestCase;

class OrderDeliveryStoreLegacyTest extends TestCase
{
    public function testLegacyFieldIsPartOfTheTable(): void
    {
        $names = array_map(static fn($f) => $f->getName(), Init::orderTables()[\Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity::class]);
        self::assertContains('legacy', $names, 'a clean install creates it too');
    }

    public function testChoosingAPointClearsTheUnmatchedMark(): void
    {
        self::assertSame('migrated', OrderDeliveryStore::legacyAfterChoice('unmatched'));
        self::assertSame('migrated', OrderDeliveryStore::legacyAfterChoice('migrated'));
        self::assertNull(OrderDeliveryStore::legacyAfterChoice(null));
    }
}
