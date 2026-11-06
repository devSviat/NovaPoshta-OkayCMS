{if $np_order_delivery}
    <tr valign="top">
        <td class="es-p5t es-p5b" width="180px"><span>{$btr->sviat__novaposhta__order_city|escape}:</span></td>
        <td class="es-p5t es-p5b"><span>{$np_order_delivery.city|escape}</span></td>
    </tr>
    <tr valign="top">
        <td class="es-p5t es-p5b" width="180px"><span>{$btr->sviat__novaposhta__order_point|escape}:</span></td>
        <td class="es-p5t es-p5b"><span>{$np_order_delivery.point|escape}</span></td>
    </tr>
{/if}
