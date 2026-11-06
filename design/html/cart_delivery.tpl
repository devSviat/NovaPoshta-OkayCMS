{if $np_module_id && $delivery->module_id == $np_module_id && isset($np_prefill[$delivery->id])}
{$np_choice = $np_prefill[$delivery->id]}
{if $np_unavailable}
<p class="np-cart-unavailable" role="status">{$lang->sviat__novaposhta__cart_dictionary_empty|escape}</p>
{else}
<div class="np-cart np-cart--{$np_theme.family|escape}"
     data-delivery-id="{$delivery->id|escape}"
     data-settlement="{$np_choice.settlement_json|escape}"
     data-division="{$np_choice.division_json|escape}"
     data-texts="{$np_texts_json|escape}"
     data-chip="{$np_theme.chip|escape}"
     data-chip-selected="{$np_theme.chipSelected|escape}">

    {if $np_popular}
        <div class="np-cart__popular {$np_theme.chips|escape}" role="group" aria-label="{$lang->sviat__novaposhta__cart_popular|escape}">
            {foreach $np_popular as $city}
                <button type="button" class="{$np_theme.chip|escape}" aria-pressed="false" data-np-city="{$city.id|escape}" data-np-city-name="{$city.label|escape}">{$city.label|escape}</button>
            {/foreach}
        </div>
    {/if}

    <div class="np-cart__city {$np_theme.field|escape}">
        {if $np_theme.family == 'vibe'}
            <label class="{$np_theme.label|escape}" for="np_city_{$delivery->id|escape}">{$lang->sviat__novaposhta__cart_city|escape}*</label>
        {/if}
        <input id="np_city_{$delivery->id|escape}" class="np-cart__city-input {$np_theme.input|escape}" type="text" autocomplete="off"
               role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="np_city_list_{$delivery->id|escape}"
               value="{if $np_choice.settlement}{$np_choice.settlement.name|escape}{/if}"
               {if $np_theme.family == 'vibe'}placeholder="{$lang->sviat__novaposhta__cart_city_placeholder|escape}"{/if}>
        {if $np_theme.family != 'vibe'}
            <span class="{$np_theme.label|escape}">{$lang->sviat__novaposhta__cart_city|escape}*</span>
        {/if}
        <ul id="np_city_list_{$delivery->id|escape}" class="np-cart__suggest" role="listbox" hidden></ul>
    </div>

    <div class="np-cart__points" hidden>
        <div class="np-cart__groups {$np_theme.chips|escape}" role="group" aria-label="{$lang->sviat__novaposhta__cart_groups|escape}"></div>
        <div class="np-cart__search {$np_theme.field|escape}" hidden>
            {if $np_theme.family == 'vibe'}
                <label class="{$np_theme.label|escape}" for="np_search_{$delivery->id|escape}">{$lang->sviat__novaposhta__cart_search|escape}</label>
            {/if}
            <input id="np_search_{$delivery->id|escape}" class="np-cart__search-input {$np_theme.input|escape}" type="search" autocomplete="off"
                   {if $np_theme.family == 'vibe'}placeholder="{$lang->sviat__novaposhta__cart_search_placeholder|escape}"{/if}>
            {if $np_theme.family != 'vibe'}
                <span class="{$np_theme.label|escape}">{$lang->sviat__novaposhta__cart_search|escape}</span>
            {/if}
        </div>
        <div class="np-cart__list" role="listbox" aria-label="{$lang->sviat__novaposhta__cart_points|escape}"></div>
        <p class="np-cart__more" hidden></p>
    </div>

    <p class="np-cart__message" role="status" aria-live="polite" hidden></p>
    <p class="np-cart__choice" hidden></p>

    <input type="hidden" class="np-cart__settlement-id" name="novaposhta[{$delivery->id|escape}][settlement_id]" value="{if $np_choice.settlement}{$np_choice.settlement.id|escape}{/if}">
    <input type="hidden" class="np-cart__division-id" name="novaposhta[{$delivery->id|escape}][division_id]" value="{if $np_choice.division}{$np_choice.division.id|escape}{/if}">
</div>
{/if}
{/if}
