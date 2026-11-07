{* Payment method page, "Additional settings": Nova Poshta cash on delivery. Shown for "Manual" payments only. *}
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
    <div class="np-codpay__kinds{if !$np_codpay.kind} hidden{/if}">
        <label class="np-codpay__kind"><input type="radio" name="sviat_np_cod_kind" value="money" {if $np_codpay.kind != 'control'}checked{/if}> <span><b>{$btr->sviat__novaposhta__cod_kind_money|escape}</b><br><span class="text_grey">{$btr->sviat__novaposhta__codpay_money_note|escape}</span></span></label>
        <label class="np-codpay__kind"><input type="radio" name="sviat_np_cod_kind" value="control" {if $np_codpay.kind == 'control'}checked{/if}> <span><b>{$btr->sviat__novaposhta__waybill_cod_control_title|escape}</b><br><span class="text_grey">{$btr->sviat__novaposhta__codpay_control_note|escape}</span></span></label>
    </div>
</div>
