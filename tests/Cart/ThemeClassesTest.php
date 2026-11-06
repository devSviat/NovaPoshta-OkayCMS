<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Cart\ThemeClasses;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ThemeClassesTest extends TestCase
{
    public static function themes(): array
    {
        return [
            ['okay_shop', 'okay'], ['broken', 'okay'], ['broken_new', 'okay'], ['my_custom_theme', 'okay'], ['', 'okay'],
            ['vibe_shop', 'vibe'], ['vibe_shop_custom', 'vibe'],
        ];
    }

    #[DataProvider('themes')]
    public function testFamily(string $theme, string $family): void
    {
        self::assertSame($family, ThemeClasses::for($theme)['family']);
    }

    public function testOkayFamilyUsesTheThemeFormClasses(): void
    {
        $classes = ThemeClasses::for('broken_new');

        self::assertSame('form__group', $classes['field']);
        self::assertSame('form__input form__placeholder--focus', $classes['input']);
        self::assertSame('form__placeholder', $classes['label']);
    }

    public function testVibeUsesItsOwnFieldsAndChips(): void
    {
        $classes = ThemeClasses::for('vibe_shop');

        self::assertSame('vs-form__row', $classes['field']);
        self::assertSame('vs-field vs-form__input', $classes['input']);
        self::assertSame('vs-form__label', $classes['label']);
        self::assertSame('vs-chip', $classes['chip']);
        self::assertSame('vs-chip--selected', $classes['chipSelected']);
    }

    public function testEveryFamilyHasEveryKey(): void
    {
        self::assertSame(array_keys(ThemeClasses::for('okay_shop')), array_keys(ThemeClasses::for('vibe_shop')));
    }
}
