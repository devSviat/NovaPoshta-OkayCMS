<?php

namespace Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Init\Init;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryStore;
use PHPUnit\Framework\TestCase;

class OrderDeliveryStoreLegacyTest extends TestCase
{
    public function testLegacyValuesKeepOnlyKnownColumnsAndCut(): void
    {
        $values = OrderDeliveryStore::legacyValues([
            'mode' => 'address', 'house' => str_repeat('1', 20), 'address_note' => str_repeat('я', 60),
            'order_id' => 5, 'evil' => 'x', 'legacy' => 'unmatched', 'settlement_name' => 'місто Київ',
        ]);

        self::assertArrayNotHasKey('evil', $values);
        self::assertArrayNotHasKey('order_id', $values, 'the store sets the order itself');
        self::assertSame(10, mb_strlen($values['house']));
        self::assertSame(40, mb_strlen($values['address_note']));
        self::assertSame('unmatched', $values['legacy']);
        self::assertSame('місто Київ', $values['settlement_name']);
    }

    public function testLegacyFieldIsPartOfTheTable(): void
    {
        $names = array_map(static fn($f) => $f->getName(), Init::orderTables()[\Okay\Modules\Sviat\NovaPoshta\Entities\OrderDeliveryEntity::class]);
        self::assertContains('legacy', $names, 'a clean install creates it too');
    }
}
