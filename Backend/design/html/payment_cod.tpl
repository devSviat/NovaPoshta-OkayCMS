{* Сторінка способу оплати, «Додаткові налаштування»: післяплата Нової Пошти. Показується лише для оплат "Manual". *}
<div class="activity_of_switch_item np-codpay{if !$np_codpay.manual} hidden{/if}">
    <input type="hidden" name="sviat_np_cod_form" value="1">
    <div class="okay_switch clearfix">
        <label class="switch_label">
            {$btr->sviat__novaposhta__codpay_title|escape}
            <i class="fn_tooltips" title="{$btr->sviat__novaposhta__codpay_tip|escape}">{include file='svg_icon.tpl' svgId='icon_tooltips'}</i>
        </label>
        <label class="switch switch-default">
            <input class="switch-input np-codpay__switch" name="sviat_np_cod" value="1" type="checkbox" {if $np_codpay.kind}checked=""{/if}/>
            <span class="switch-label"></span>
            <span class="switch-handle"></span>
        </label>
    </div>
    {* Контроль оплати першим: саме він для магазину з договором NovaPay (ФОП чи компанія). *}
    <div class="np-codpay__kinds{if !$np_codpay.kind} hidden{/if}">
        <div class="okay_type_radio_wrap">
            <input id="np_codpay_control" class="hidden_check" type="radio" name="sviat_np_cod_kind" value="control" {if $np_codpay.kind == 'control'}checked{/if}>
            <label for="np_codpay_control" class="okay_type_radio"><span><b>{$btr->sviat__novaposhta__codpay_control_title|escape}</b> <span class="text_grey">{$btr->sviat__novaposhta__codpay_control_note|escape}</span></span>
                <i class="fn_tooltips np_tip hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->sviat__novaposhta__codpay_control_tip|escape}" tabindex="0" role="img" aria-label="{$btr->sviat__novaposhta__codpay_control_tip|escape}">{include file='svg_icon.tpl' svgId='icon_tooltips'}</i>
            </label>
        </div>
        <div class="okay_type_radio_wrap">
            <input id="np_codpay_money" class="hidden_check" type="radio" name="sviat_np_cod_kind" value="money" {if $np_codpay.kind != 'control'}checked{/if}>
            <label for="np_codpay_money" class="okay_type_radio"><span><b>{$btr->sviat__novaposhta__codpay_money_title|escape}</b> <span class="text_grey">{$btr->sviat__novaposhta__codpay_money_note|escape}</span></span>
                <i class="fn_tooltips np_tip hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->sviat__novaposhta__codpay_money_tip|escape}" tabindex="0" role="img" aria-label="{$btr->sviat__novaposhta__codpay_money_tip|escape}">{include file='svg_icon.tpl' svgId='icon_tooltips'}</i>
            </label>
        </div>
    </div>
</div>
