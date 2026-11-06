{if $np_order_delivery}
{if $np_order_delivery.address}{$np_a = $np_order_delivery.address}{capture name=np_addr}{$np_a.street|escape}, {$np_a.house|escape}{if $np_a.flat}, {$btr->sviat__novaposhta__order_flat_short|escape} {$np_a.flat|escape}{/if}{if $np_a.entrance}, {$btr->sviat__novaposhta__order_entrance|escape} {$np_a.entrance|escape}{/if}{if $np_a.floor}, {$btr->sviat__novaposhta__order_floor|escape} {$np_a.floor|escape}{/if}{if $np_a.elevator}, {$btr->sviat__novaposhta__order_elevator|escape}{/if}{if $np_a.note}. {$btr->sviat__novaposhta__order_note|escape}: {$np_a.note|escape}{/if}{/capture}{/if}
<div class="np-order mb-1">
    <div class="heading_label">{$btr->sviat__novaposhta__order_title|escape}</div>
    {if $np_order_delivery.priceUnconfirmed}
        <div class="alert alert--icon alert--warning mb-h">
            <div class="alert__content"><p>{$btr->sviat__novaposhta__order_price_fallback|escape}</p></div>
        </div>
    {/if}
    <div class="mb-h"><span class="text_600">{$btr->sviat__novaposhta__order_city|escape}:</span> {$np_order_delivery.city|escape}</div>
    {if $np_order_delivery.point}
        <div class="mb-h"><span class="text_600">{$btr->sviat__novaposhta__order_point|escape}:</span> {$np_order_delivery.point|escape}</div>
    {/if}
    {if $np_order_delivery.address}
        <div class="mb-h"><span class="text_600">{$btr->sviat__novaposhta__order_address|escape}:</span> {$smarty.capture.np_addr}</div>
    {/if}
    {if $np_order_delivery.lifting}
        <div class="mb-h"><span class="text_600">{$btr->sviat__novaposhta__order_lifting|escape}:</span> {$btr->sviat__novaposhta__order_lifting_yes|escape}</div>
    {/if}
    {if $np_order_delivery.term}
        <div class="mb-h"><span class="text_600">{$btr->sviat__novaposhta__order_term|escape}:</span> {$np_order_delivery.term|escape}</div>
    {/if}
</div>
{/if}
