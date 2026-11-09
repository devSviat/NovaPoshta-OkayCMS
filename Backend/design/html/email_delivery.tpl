{if $np_order_delivery}
{if $np_order_delivery.address}{$np_a = $np_order_delivery.address}{capture name=np_addr}{$np_a.street|escape}, {$np_a.house|escape}{if $np_a.flat}, {$btr->sviat__novaposhta__order_flat_short|escape} {$np_a.flat|escape}{/if}{if $np_a.entrance}, {$btr->sviat__novaposhta__order_entrance|escape} {$np_a.entrance|escape}{/if}{if $np_a.floor}, {$btr->sviat__novaposhta__order_floor|escape} {$np_a.floor|escape}{/if}{if $np_a.elevator}, {$btr->sviat__novaposhta__order_elevator|escape}{/if}{if $np_a.note}. {$btr->sviat__novaposhta__order_note|escape}: {$np_a.note|escape}{/if}{/capture}{/if}
    <tr valign="top">
        <td class="es-p5t es-p5b" width="180px"><span>{$btr->sviat__novaposhta__order_city|escape}:</span></td>
        <td class="es-p5t es-p5b"><span>{$np_order_delivery.city|escape}</span></td>
    </tr>
    {if $np_order_delivery.point}
    <tr valign="top">
        <td class="es-p5t es-p5b" width="180px"><span>{$btr->sviat__novaposhta__order_point|escape}:</span></td>
        <td class="es-p5t es-p5b"><span>{$np_order_delivery.point|escape}</span></td>
    </tr>
    {/if}
    {if $np_order_delivery.address}
    <tr valign="top">
        <td class="es-p5t es-p5b" width="180px"><span>{$btr->sviat__novaposhta__order_address|escape}:</span></td>
        <td class="es-p5t es-p5b"><span>{$smarty.capture.np_addr}</span></td>
    </tr>
    {/if}
    {if $np_order_delivery.lifting}
    <tr valign="top">
        <td class="es-p5t es-p5b" width="180px"><span>{$btr->sviat__novaposhta__order_lifting|escape}:</span></td>
        <td class="es-p5t es-p5b"><span>{$btr->sviat__novaposhta__order_lifting_yes|escape}</span></td>
    </tr>
    {/if}
    {if $np_order_delivery.term}
    <tr valign="top">
        <td class="es-p5t es-p5b" width="180px"><span>{$btr->sviat__novaposhta__order_term|escape}:</span></td>
        <td class="es-p5t es-p5b"><span>{$np_order_delivery.term|escape}</span></td>
    </tr>
    {/if}
    {if $np_order_delivery.codFee}
    <tr valign="top">
        <td class="es-p5t es-p5b" width="180px"><span>{$btr->sviat__novaposhta__order_cod_fee|escape}:</span></td>
        <td class="es-p5t es-p5b"><span>{$np_order_delivery.codFee|convert:$currency->id} {$currency->sign|escape}</span></td>
    </tr>
    {/if}
    {if $np_order_delivery.priceUnconfirmed}
    <tr valign="top">
        <td class="es-p5t es-p5b" colspan="2"><span style="color:#a35a00;">{$btr->sviat__novaposhta__order_price_fallback|escape}</span></td>
    </tr>
    {/if}
{/if}
