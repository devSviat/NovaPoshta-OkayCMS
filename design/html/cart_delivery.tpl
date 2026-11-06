{function name='np_field' id='' field='' value='' label='' cls='' max='' mode='text' required=false}
    <div class="np-cart__field {$cls|escape} {$np_theme.field|escape}">
        {if $np_theme.family == 'vibe'}
            <label class="{$np_theme.label|escape}" for="{$id|escape}">{$label|escape}{if $required}*{/if}</label>
        {/if}
        <input id="{$id|escape}" class="{$cls|escape}-input {$np_theme.input|escape}" type="text" name="{$field|escape}" value="{$value|escape}" autocomplete="off"{if $max} maxlength="{$max|escape}"{/if}{if $mode == 'numeric'} inputmode="numeric"{/if}>
        {if $np_theme.family != 'vibe'}
            <span class="{$np_theme.label|escape}">{$label|escape}{if $required}*{/if}</span>
        {/if}
    </div>
{/function}
{if $np_module_id && $delivery->module_id == $np_module_id && isset($np_prefill[$delivery->id])}
{$np_choice = $np_prefill[$delivery->id]}
{$np_mode = $np_modes[$delivery->id]|default:'points'}
{$np_address = $np_choice.address|default:[]}
{if $np_unavailable}
<p class="np-cart-unavailable" role="status">{$lang->sviat__novaposhta__cart_dictionary_empty|escape}</p>
{else}
<div class="np-cart np-cart--{$np_theme.family|escape}"
     data-delivery-id="{$delivery->id|escape}"
     data-mode="{$np_mode|escape}"
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

    {if $np_mode == 'address'}
    {$np_name = "novaposhta[`$delivery->id`]"}
    <div class="np-cart__address" hidden>
        <div class="np-cart__street">
            {call name='np_field' id="np_street_`$delivery->id`" field="`$np_name`[street]" value=$np_address.street|default:'' label=$lang->sviat__novaposhta__cart_street cls='np-cart__street' max=100 required=true}
            <ul class="np-cart__street-suggest np-cart__suggest" role="listbox" hidden></ul>
            <p class="np-cart__street-hint" hidden></p>
            <input type="hidden" class="np-cart__street-ref" name="{$np_name|escape}[street_ref]" value="{$np_address.street_ref|default:''|escape}">
        </div>
        <div class="np-cart__address-row">
            {call name='np_field' id="np_house_`$delivery->id`" field="`$np_name`[house]" value=$np_address.house|default:'' label=$lang->sviat__novaposhta__cart_house cls='np-cart__house' max=10 required=true}
            {call name='np_field' id="np_flat_`$delivery->id`" field="`$np_name`[flat]" value=$np_address.flat|default:'' label=$lang->sviat__novaposhta__cart_flat cls='np-cart__flat' max=10}
            {call name='np_field' id="np_entrance_`$delivery->id`" field="`$np_name`[entrance]" value=$np_address.entrance|default:'' label=$lang->sviat__novaposhta__cart_entrance cls='np-cart__entrance' max=5}
            {call name='np_field' id="np_floor_`$delivery->id`" field="`$np_name`[floor]" value=$np_address.floor|default:'' label=$lang->sviat__novaposhta__cart_floor cls='np-cart__floor' max=3 mode='numeric'}
        </div>
        {$np_check = $np_theme.check}
        <div class="np-cart__note">
            {call name='np_field' id="np_note_`$delivery->id`" field="`$np_name`[note]" value=$np_address.note|default:'' label=$lang->sviat__novaposhta__cart_note cls='np-cart__note' max=40}
            <span class="np-cart__note-count" aria-live="polite"></span>
        </div>
        <div class="np-cart__lifting" hidden>
            <label class="{$np_check|escape} np-cart__check">
                <input type="checkbox" class="{$np_check|escape}__input np-cart__lifting-input" name="{$np_name|escape}[lifting]" value="1"{if !empty($np_address.lifting)} checked{/if}>
                <svg class="{$np_check|escape}__icon" viewBox="0 0 20 20" aria-hidden="true"><path class="{$np_check|escape}__mark" fill="none" d="M4 10 l5 4 8-8.5"></path></svg>
                <span><b class="np-cart__lifting-title">{$lang->sviat__novaposhta__cart_lifting|escape}</b> <span class="np-cart__lifting-fee"></span></span>
            </label>
            <p class="np-cart__lifting-note"></p>
            {* The lift only matters to Nova Poshta together with lifting (getDocumentPrice, the shipment). *}
            <label class="{$np_check|escape} np-cart__check np-cart__elevator-check"{if empty($np_address.lifting)} hidden{/if}>
                <input type="checkbox" class="{$np_check|escape}__input np-cart__elevator" name="{$np_name|escape}[elevator]" value="1"{if !empty($np_address.elevator)} checked{/if}>
                <svg class="{$np_check|escape}__icon" viewBox="0 0 20 20" aria-hidden="true"><path class="{$np_check|escape}__mark" fill="none" d="M4 10 l5 4 8-8.5"></path></svg>
                <span>{$lang->sviat__novaposhta__cart_elevator|escape}</span>
            </label>
        </div>
    </div>
    {/if}

    <p class="np-cart__message" role="status" aria-live="polite" hidden></p>
    <p class="np-cart__choice" hidden></p>
    <div class="np-cart__quote" role="status" aria-live="polite" hidden></div>

    <input type="hidden" class="np-cart__settlement-id" name="novaposhta[{$delivery->id|escape}][settlement_id]" value="{if $np_choice.settlement}{$np_choice.settlement.id|escape}{/if}">
    <input type="hidden" class="np-cart__division-id" name="novaposhta[{$delivery->id|escape}][division_id]" value="{if $np_choice.division}{$np_choice.division.id|escape}{/if}">
</div>
{/if}
{/if}
