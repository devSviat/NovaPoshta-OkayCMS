<?php

namespace Modules\Sviat\NovaPoshta\Products;

use Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume;
use PHPUnit\Framework\TestCase;

class VariantVolumeTest extends TestCase
{
    public function testWhatTheManagerTypes(): void
    {
        self::assertSame(0.5, VariantVolume::clean('0,5'));
        self::assertSame(0.012, VariantVolume::clean(' 0.012 '));
        self::assertSame(0.12346, VariantVolume::clean('0.123456'));
        self::assertSame(0.0, VariantVolume::clean('-1'));
        self::assertSame(0.0, VariantVolume::clean('abc'));
        self::assertSame(0.0, VariantVolume::clean(''));
        self::assertSame(0.0, VariantVolume::clean(['1']));
    }
}
