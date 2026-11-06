{if $np_order_delivery}
{if $np_order_delivery.address}{$np_a = $np_order_delivery.address}{capture name=np_addr}{$np_a.street|escape}, {$np_a.house|escape}{if $np_a.flat}, {$lang->sviat__novaposhta__order_flat_short|escape} {$np_a.flat|escape}{/if}{if $np_a.entrance}, {$lang->sviat__novaposhta__order_entrance|escape} {$np_a.entrance|escape}{/if}{if $np_a.floor}, {$lang->sviat__novaposhta__order_floor|escape} {$np_a.floor|escape}{/if}{if $np_a.elevator}, {$lang->sviat__novaposhta__order_elevator|escape}{/if}{if $np_a.note}. {$lang->sviat__novaposhta__order_note|escape}: {$np_a.note|escape}{/if}{/capture}{/if}
    {if $np_order_family == 'vibe'}
        <dt><span data-language="sviat__novaposhta__order_city">{$lang->sviat__novaposhta__order_city|escape}</span></dt>
        <dd>{$np_order_delivery.city|escape}</dd>
        {if $np_order_delivery.point}
            <dt><span data-language="sviat__novaposhta__order_point">{$lang->sviat__novaposhta__order_point|escape}</span></dt>
            <dd>{$np_order_delivery.point|escape}</dd>
        {/if}
        {if $np_order_delivery.address}
            <dt><span data-language="sviat__novaposhta__order_address">{$lang->sviat__novaposhta__order_address|escape}</span></dt>
            <dd>{$smarty.capture.np_addr}</dd>
        {/if}
        {if $np_order_delivery.lifting}
            <dt><span data-language="sviat__novaposhta__order_lifting">{$lang->sviat__novaposhta__order_lifting|escape}</span></dt>
            <dd>{$lang->sviat__novaposhta__order_lifting_yes|escape}</dd>
        {/if}
        {if $np_order_delivery.priceUnconfirmed}
            <dt><span data-language="sviat__novaposhta__order_delivery_cost">{$lang->sviat__novaposhta__order_delivery_cost|escape}</span></dt>
            <dd>{$lang->sviat__novaposhta__cart_price_by_manager|escape}</dd>
        {/if}
        {if $np_order_delivery.term}
            <dt><span data-language="sviat__novaposhta__order_term">{$lang->sviat__novaposhta__order_term|escape}</span></dt>
            <dd>{$np_order_delivery.term|escape}</dd>
        {/if}
    {else}
        <tr>
            <td><span data-language="sviat__novaposhta__order_city">{$lang->sviat__novaposhta__order_city|escape}</span></td>
            <td>{$np_order_delivery.city|escape}</td>
        </tr>
        {if $np_order_delivery.point}
            <tr>
                <td><span data-language="sviat__novaposhta__order_point">{$lang->sviat__novaposhta__order_point|escape}</span></td>
                <td>{$np_order_delivery.point|escape}</td>
            </tr>
        {/if}
        {if $np_order_delivery.address}
            <tr>
                <td><span data-language="sviat__novaposhta__order_address">{$lang->sviat__novaposhta__order_address|escape}</span></td>
                <td>{$smarty.capture.np_addr}</td>
            </tr>
        {/if}
        {if $np_order_delivery.lifting}
            <tr>
                <td><span data-language="sviat__novaposhta__order_lifting">{$lang->sviat__novaposhta__order_lifting|escape}</span></td>
                <td>{$lang->sviat__novaposhta__order_lifting_yes|escape}</td>
            </tr>
        {/if}
        {if $np_order_delivery.priceUnconfirmed}
            <tr>
                <td><span data-language="sviat__novaposhta__order_delivery_cost">{$lang->sviat__novaposhta__order_delivery_cost|escape}</span></td>
                <td>{$lang->sviat__novaposhta__cart_price_by_manager|escape}</td>
            </tr>
        {/if}
        {if $np_order_delivery.term}
            <tr>
                <td><span data-language="sviat__novaposhta__order_term">{$lang->sviat__novaposhta__order_term|escape}</span></td>
                <td>{$np_order_delivery.term|escape}</td>
            </tr>
        {/if}
    {/if}
{/if}
