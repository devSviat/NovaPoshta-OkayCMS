{if $np_order_delivery}
    {if $np_order_family == 'vibe'}
        <dt><span data-language="sviat__novaposhta__order_city">{$lang->sviat__novaposhta__order_city|escape}</span></dt>
        <dd>{$np_order_delivery.city|escape}</dd>
        <dt><span data-language="sviat__novaposhta__order_point">{$lang->sviat__novaposhta__order_point|escape}</span></dt>
        <dd>{$np_order_delivery.point|escape}</dd>
    {else}
        <tr>
            <td><span data-language="sviat__novaposhta__order_city">{$lang->sviat__novaposhta__order_city|escape}</span></td>
            <td>{$np_order_delivery.city|escape}</td>
        </tr>
        <tr>
            <td><span data-language="sviat__novaposhta__order_point">{$lang->sviat__novaposhta__order_point|escape}</span></td>
            <td>{$np_order_delivery.point|escape}</td>
        </tr>
    {/if}
{/if}
