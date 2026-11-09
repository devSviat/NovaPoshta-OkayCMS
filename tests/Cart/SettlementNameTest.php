<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Cart\SettlementName;
use PHPUnit\Framework\TestCase;

class SettlementNameTest extends TestCase
{
    /** A city goes by its name alone; the type tells namesakes apart for the rest, «смт» as people write it. */
    public function testTypeAndTitle(): void
    {
        self::assertSame(['', 'Київ'], SettlementName::split('місто Київ'));
        self::assertSame(['смт', 'Калинівка'], SettlementName::split('селище міського типу Калинівка'));
        self::assertSame(['село', 'Калинівка'], SettlementName::split('село Калинівка'));
        self::assertSame(['селище', 'Запоріжжя'], SettlementName::split('селище Запоріжжя'));
        self::assertSame(['', 'Калинівка (Калинівська територіальна громада)'], SettlementName::split('місто Калинівка (Калинівська територіальна громада)'));
        self::assertSame(['', 'Сергіївка'], SettlementName::split('Сергіївка'));
        self::assertSame(['', 'Kyiv'], SettlementName::split('Kyiv'));
    }
}
