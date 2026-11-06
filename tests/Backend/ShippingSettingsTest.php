<?php

namespace Modules\Sviat\NovaPoshta\Backend;

use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;
use Okay\Modules\Sviat\NovaPoshta\Quote\ShippingConfig;
use PHPUnit\Framework\TestCase;

class ShippingSettingsTest extends TestCase
{
    private static function parse(array $post): array
    {
        return ShippingSettings::parse($post, static fn(int $id): bool => $id === 2200878);
    }

    public function testValidInputIsStored(): void
    {
        $parsed = self::parse(['sender_division_id' => '2200878', 'default_weight' => '1,5', 'default_volume' => '0.002', 'show_term' => '1']);

        self::assertSame([], $parsed['errors']);
        self::assertSame([
            ShippingSettings::SENDER => 2200878,
            ShippingSettings::WEIGHT => 1.5,
            ShippingSettings::VOLUME => 0.002,
            ShippingSettings::SHOW_TERM => 1,
        ], $parsed['values']);
    }

    public function testUnknownDivisionIsRejected(): void
    {
        self::assertContains('shipping_error_sender', self::parse(['sender_division_id' => '5', 'default_weight' => '1', 'default_volume' => '0.001'])['errors']);
    }

    public function testEmptySenderClearsIt(): void
    {
        $parsed = self::parse(['sender_division_id' => '', 'default_weight' => '1', 'default_volume' => '0.001']);

        self::assertSame([], $parsed['errors']);
        self::assertNull($parsed['values'][ShippingSettings::SENDER]);
    }

    public function testWeightAndVolumeOutOfRangeAreRejected(): void
    {
        foreach (['0', '-1', 'abc', '1001', ''] as $weight) {
            self::assertContains('shipping_error_weight', self::parse(['default_weight' => $weight, 'default_volume' => '0.001'])['errors'], $weight);
        }
        foreach (['0', '-0.1', 'x', '11'] as $volume) {
            self::assertContains('shipping_error_volume', self::parse(['default_weight' => '1', 'default_volume' => $volume])['errors'], $volume);
        }
        self::assertContains('shipping_error_weight', self::parse(['default_weight' => ['1'], 'default_volume' => '0.001'])['errors']);
    }

    public function testMissingTermMeansOffAndCodKindIsNotTouched(): void
    {
        $values = self::parse(['default_weight' => '1', 'default_volume' => '0.001', 'cod_kind' => 'control'])['values'];

        self::assertSame(0, $values[ShippingSettings::SHOW_TERM]);
        self::assertArrayNotHasKey(ShippingSettings::COD_KIND, $values, 'the cash-on-delivery form owns it');
    }

    public function testConfigFromStoredValuesWithDefaults(): void
    {
        $config = ShippingConfig::fromSettings(static fn(string $name) => null);

        self::assertNull($config->senderDivisionId);
        self::assertSame(1.0, $config->defaultWeight);
        self::assertSame(0.001, $config->defaultVolume);
        self::assertSame(CodKind::Money, $config->codKind);
        self::assertFalse($config->showTerm);

        $stored = [ShippingSettings::SENDER => '42', ShippingSettings::WEIGHT => '2.5', ShippingSettings::VOLUME => '0.01', ShippingSettings::COD_KIND => 'control', ShippingSettings::SHOW_TERM => '1'];
        $config = ShippingConfig::fromSettings(static fn(string $name) => $stored[$name] ?? null);
        self::assertSame(42, $config->senderDivisionId);
        self::assertSame(2.5, $config->defaultWeight);
        self::assertSame(CodKind::Control, $config->codKind);
        self::assertTrue($config->showTerm);
    }
}
