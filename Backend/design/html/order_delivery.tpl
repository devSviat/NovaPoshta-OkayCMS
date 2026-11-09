{if $np_order_point || $np_order_delivery}
{if $np_order_delivery.address}{$np_a = $np_order_delivery.address}{capture name=np_addr}{$np_a.street|escape}, {$np_a.house|escape}{if $np_a.flat}, {$btr->sviat__novaposhta__order_flat_short|escape} {$np_a.flat|escape}{/if}{if $np_a.entrance}, {$btr->sviat__novaposhta__order_entrance|escape} {$np_a.entrance|escape}{/if}{if $np_a.floor}, {$btr->sviat__novaposhta__order_floor|escape} {$np_a.floor|escape}{/if}{if $np_a.elevator}, {$btr->sviat__novaposhta__order_elevator|escape}{/if}{if $np_a.note}. {$btr->sviat__novaposhta__order_note|escape}: {$np_a.note|escape}{/if}{/capture}{/if}
{$p = $np_order_point}
<div class="np-order mb-1{if $p} fn_np_order_point{/if}"{if $p} data-url="{$smarty.server.SCRIPT_NAME|escape}?controller=Sviat.NovaPoshta.OrderPointAdmin" data-order="{$p.orderId|escape}" data-token="{$smarty.session.id|escape}" data-mode="{$p.mode|escape}" data-currency="{$p.currency|escape}" data-lifting="{if $p.showLifting}1{/if}" data-settlement-id="{$p.settlementId|escape}" data-settlement-name="{$p.settlementName|escape}" data-settlement-type="{$p.settlementType|escape}" data-settlement-title="{$p.settlementTitle|escape}" data-settlement-area="{$p.settlementArea|escape}" data-settlement-district="{$p.settlementDistrict|escape}" data-texts="{$p.textsJson|escape}" data-open="{if $p.open}1{/if}"{if $np_order_delivery.address} data-street="{$np_a.street|escape}" data-street-ref="{$np_a.streetRef|escape}" data-house="{$np_a.house|escape}" data-flat="{$np_a.flat|escape}" data-entrance="{$np_a.entrance|escape}" data-floor="{$np_a.floor|escape}" data-note="{$np_a.note|escape}" data-lifting-on="{if $np_order_delivery.lifting}1{/if}" data-elevator="{if $np_a.elevator}1{/if}"{/if}{/if}>
    <div class="np-op__head">
        <span class="heading_label">{$btr->sviat__novaposhta__order_title|escape}</span>
        {if $p && !$p.locked && !$p.needsChoice}<button type="button" class="np-op__link fn_np_op_change">{$btr->sviat__novaposhta__op_change|escape}</button>{/if}
    </div>
    {if $p && $p.needsChoice}
        <div class="alert alert--warning mb-h"><div class="alert__content"><p>{if $p.mode == 'address'}{$btr->sviat__novaposhta__op_needs_address|escape}{else}{$btr->sviat__novaposhta__op_needs_point|escape}{/if}</p></div></div>
    {/if}
    {if $np_order_delivery}
    {if $np_order_delivery.legacyUnmatched}
        <div class="alert alert--warning mb-h"><div class="alert__content"><p>{$btr->sviat__novaposhta__legacy_order_unmatched|escape}</p></div></div>
    {/if}
    {if $np_order_delivery.priceUnconfirmed}
        <div class="alert alert--icon alert--warning mb-h">
            <div class="alert__content"><p>{$btr->sviat__novaposhta__order_price_fallback|escape}</p></div>
        </div>
    {/if}
    {* Label above value, as in the waybill card: the label small and muted, the value plain. *}
    <div class="np-op__row"><span class="np-op__label">{$btr->sviat__novaposhta__order_city|escape}</span>{$np_order_delivery.city|escape}</div>
    {if $np_order_delivery.point}
        <div class="np-op__row"><span class="np-op__label">{$btr->sviat__novaposhta__order_point|escape}</span>{$np_order_delivery.point|escape}</div>
    {/if}
    {if $np_order_delivery.address}
        <div class="np-op__row"><span class="np-op__label">{$btr->sviat__novaposhta__order_address|escape}</span>{$smarty.capture.np_addr}</div>
    {/if}
    {if $np_order_delivery.lifting}
        <div class="np-op__row"><span class="np-op__label">{$btr->sviat__novaposhta__order_lifting|escape}</span>{$btr->sviat__novaposhta__order_lifting_yes|escape}</div>
    {/if}
    {if $np_order_delivery.codFee}
        <div class="np-op__row"><span class="np-op__label">{$btr->sviat__novaposhta__order_cod_fee_short|escape}</span>{$np_order_delivery.codFee|string_format:'%.2f'|replace:'.':','|escape} {$currency->sign|escape}
            <span class="np-op__hint">{$btr->sviat__novaposhta__order_cod_fee_note|escape}{if !$np_order_delivery.codFeePublic}; {$btr->sviat__novaposhta__order_cod_fee_hidden|escape}{/if}</span></div>
    {/if}
    {if $np_order_delivery.term}
        <div class="np-op__row"><span class="np-op__label">{$btr->sviat__novaposhta__order_term|escape}</span>{$np_order_delivery.term|escape}</div>
    {/if}
    {/if}
    {if $p && $p.locked}
        <div class="text_grey mb-h">{$btr->sviat__novaposhta__op_locked|escape}</div>
    {/if}
    {if $p}
        <div class="np-op__message" role="status"></div>
        <div class="np-op__editor" hidden></div>
    {/if}
</div>
{/if}
