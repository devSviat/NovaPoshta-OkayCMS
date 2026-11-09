<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

/**
 * Класи теми магазину для блоку в кошику, щоб блок виглядав як решта сторінки оформлення
 * замовлення (вимога автора). Серед відомих нам тем є дві родини розмітки: okay_shop і
 * похідні від неї теми мають спільні `form__*`; vibe_shop має власні
 * компоненти `vs-*`. Невідома тема вважається похідною від okay_shop.
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
                // У vibe_shop немає компонента чекбокса: модуль малює власний, у кольорах теми.
                'check' => 'np-check',
            ];
        }

        return [
            'family' => 'okay',
            'field' => 'form__group',
            'input' => 'form__input form__placeholder--focus',
            'label' => 'form__placeholder',
            // У цих тем немає компонента chip; модуль малює власний у кольорах теми.
            'chips' => 'np-chips',
            'chip' => 'np-chip',
            'chipSelected' => 'np-chip--selected',
            'check' => 'checkbox',
        ];
    }
}
