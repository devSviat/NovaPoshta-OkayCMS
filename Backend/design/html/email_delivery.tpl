{if $np_order_delivery}
    <tr valign="top">
        <td class="es-p5t es-p5b" width="180px"><span>{$btr->sviat__novaposhta__order_city|escape}:</span></td>
        <td class="es-p5t es-p5b"><span>{$np_order_delivery.city|escape}</span></td>
    </tr>
    <tr valign="top">
        <td class="es-p5t es-p5b" width="180px"><span>{$btr->sviat__novaposhta__order_point|escape}:</span></td>
        <td class="es-p5t es-p5b"><span>{$np_order_delivery.point|escape}</span></td>
    </tr>
    {if $np_order_delivery.term}
    <tr valign="top">
        <td class="es-p5t es-p5b" width="180px"><span>{$btr->sviat__novaposhta__order_term|escape}:</span></td>
        <td class="es-p5t es-p5b"><span>{$np_order_delivery.term|escape}</span></td>
    </tr>
    {/if}
    {if $np_order_delivery.priceUnconfirmed}
    <tr valign="top">
        <td class="es-p5t es-p5b" colspan="2"><span style="color:#a35a00;">{$btr->sviat__novaposhta__order_price_fallback|escape}</span></td>
    </tr>
    {/if}
{/if}
