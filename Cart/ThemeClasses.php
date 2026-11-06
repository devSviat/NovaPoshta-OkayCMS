<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/**
 * Classes of the shop's theme for the cart block, so the block looks like the rest of the
 * checkout (author's requirement). Two markup families exist among the themes we know:
 * okay_shop and the themes derived from it (broken, broken_new) share `form__*`; vibe_shop
 * has its own `vs-*` components. An unknown theme is treated as okay_shop-derived.
 */
final class ThemeClasses
{
    /** @return array{family:string, field:string, input:string, label:string, chips:string, chip:string, chipSelected:string, check:string} */
    public static function for(string $theme): array
    {
        if (str_starts_with($theme, 'vibe_shop')) {
            return [
                'family' => 'vibe',
                'field' => 'vs-form__row',
                'input' => 'vs-field vs-form__input',
                'label' => 'vs-form__label',
                'chips' => 'vs-chips',
                'chip' => 'vs-chip',
                'chipSelected' => 'vs-chip--selected',
                // vibe_shop has no checkbox component: the module's own, in the theme's colours.
                'check' => 'np-check',
            ];
        }

        return [
            'family' => 'okay',
            'field' => 'form__group',
            'input' => 'form__input form__placeholder--focus',
            'label' => 'form__placeholder',
            // These themes have no chip component; the module draws one with the theme's colours.
            'chips' => 'np-chips',
            'chip' => 'np-chip',
            'chipSelected' => 'np-chip--selected',
            'check' => 'checkbox',
        ];
    }
}
