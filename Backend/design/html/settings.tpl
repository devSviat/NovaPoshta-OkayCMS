{$meta_title = $btr->sviat__novaposhta__title scope=global}

<div class="main_header">
    <div class="main_header__item">
        <div class="main_header__inner">
            <div class="box_heading heading_page">{$btr->sviat__novaposhta__title|escape}</div>
            <div class="box_btn_heading">
                <a class="btn btn_small btn-outline-info np_readme" href="https://github.com/devSviat/NovaPoshta-OkayCMS#readme" target="_blank" rel="noopener" title="{$btr->sviat__novaposhta__readme_hint|escape}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 19c-4.3 1.4 -4.3 -2.5 -6 -3m12 5v-3.5c0 -1 .1 -1.4 -.5 -2c2.8 -.3 5.5 -1.4 5.5 -6a4.6 4.6 0 0 0 -1.3 -3.2a4.2 4.2 0 0 0 -.1 -3.2s-1.1 -.3 -3.5 1.3a12.3 12.3 0 0 0 -6.2 0c-2.4 -1.6 -3.5 -1.3 -3.5 -1.3a4.2 4.2 0 0 0 -.1 3.2a4.6 4.6 0 0 0 -1.3 3.2c0 4.6 2.7 5.7 5.5 6c-.6 .6 -.6 1.2 -.5 2v3.5"/></svg>
                    <span>{$btr->sviat__novaposhta__readme|escape}</span>
                </a>
            </div>
        </div>
    </div>
</div>

{if $np_free_for_all}
    <div class="alert alert--icon alert--error">
        <div class="alert__content">
            <div class="alert__title">{$btr->sviat__novaposhta__free_for_all_title|escape}</div>
            <p>{$btr->sviat__novaposhta__free_for_all_settings|escape}</p>
            <ul class="mb-0">
                {foreach $np_free_for_all as $d}
                    <li><a href="{url controller=DeliveryAdmin id=$d->id return=$smarty.server.REQUEST_URI}">{$d->name|escape}</a></li>
                {/foreach}
            </ul>
        </div>
    </div>
{/if}

{* Вкладки ядра: активна зберігається в ?active_tab=, тож форма, надіслана на той самий URL, знову її відкриває. *}
<div class="tabs np_tabs">
    <div class="tab_navigation tab_navigation--round">
        <a href="#np_tab_main" class="tab_navigation_link">{$btr->sviat__novaposhta__tab_main|escape}</a>
        <a href="#np_tab_points" class="tab_navigation_link">{$btr->sviat__novaposhta__tab_points|escape}</a>
        <a href="#np_tab_waybills" class="tab_navigation_link">{$btr->sviat__novaposhta__tab_waybills|escape}</a>
    </div>
    <div class="tab_container">

<div id="np_tab_main" class="tab">
<div class="row">
<div class="col-xl-6 col-lg-12">

<form method="post" id="np_key_form">
    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
    <div class="boxed mb-2">
        <div class="heading_box mb-1">{$btr->sviat__novaposhta__key_box|escape}</div>
        <p class="text_grey">{$btr->sviat__novaposhta__key_intro|escape}</p>
        <div class="form-group">
            <label class="heading_label" for="np_api_key">{$btr->sviat__novaposhta__key_label|escape}</label>
            <input id="np_api_key" name="api_key" class="form-control" type="text" value=""
                   autocomplete="off" spellcheck="false"
                   placeholder="{if $np_key.has_key}{$btr->sviat__novaposhta__key_saved_prefix|escape} {$np_key.key_masked|escape}. {$btr->sviat__novaposhta__key_saved_suffix|escape}{else}{$btr->sviat__novaposhta__key_placeholder_new|escape}{/if}">
        </div>

        {if $np_key.state == 'ok'}
            <div class="np_status np_status--ok" role="status" aria-live="polite">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M9 12l2 2l4 -4"/></svg>
                <span><b>{$btr->sviat__novaposhta__state_ok_title|escape}.</b> {$btr->sviat__novaposhta__state_ok_text|escape}{if $np_key.checked_at} {$btr->sviat__novaposhta__checked_at|escape} {$np_key.checked_at|escape}.{/if}{if $np_key.just_saved} {$btr->sviat__novaposhta__saved|escape}{/if}</span>
            </div>
        {else}
            <div class="alert alert--icon {if $np_key.state == 'invalid'}alert--error{elseif $np_key.state == 'denied' || $np_key.state == 'denied_old' || $np_key.state == 'unreachable'}alert--warning{else}alert--info{/if}" role="status" aria-live="polite">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $np_key.state == 'invalid'}{$btr->sviat__novaposhta__state_invalid_title|escape}
                        {elseif $np_key.state == 'denied'}{$btr->sviat__novaposhta__state_denied_title|escape}
                        {elseif $np_key.state == 'denied_old'}{$btr->sviat__novaposhta__state_denied_old_title|escape}
                        {elseif $np_key.state == 'unreachable'}{$btr->sviat__novaposhta__state_unreachable_title|escape}
                        {elseif $np_key.state == 'unchecked'}{$btr->sviat__novaposhta__state_unchecked_title|escape}
                        {else}{$btr->sviat__novaposhta__state_none_title|escape}{/if}
                    </div>
                    <p>
                        {if $np_key.state == 'invalid'}{$btr->sviat__novaposhta__state_invalid_text|escape}
                        {elseif $np_key.state == 'denied'}{$btr->sviat__novaposhta__state_denied_text|escape}
                        {elseif $np_key.state == 'denied_old'}{$btr->sviat__novaposhta__state_denied_old_text|escape}
                        {elseif $np_key.state == 'unreachable'}{$btr->sviat__novaposhta__state_unreachable_text|escape}
                        {elseif $np_key.state == 'unchecked'}{$btr->sviat__novaposhta__state_unchecked_text|escape}
                        {else}{$btr->sviat__novaposhta__state_none_text|escape}{/if}
                        {if $np_key.just_saved} {$btr->sviat__novaposhta__saved|escape}{/if}
                        {if $np_key.kept_previous} {$btr->sviat__novaposhta__not_saved_kept|escape}
                        {elseif $np_key.rejected} {$btr->sviat__novaposhta__not_saved|escape}{/if}
                    </p>
                    <p>
                        <b>{$btr->sviat__novaposhta__what_to_do|escape}</b>
                        {if $np_key.state == 'invalid'}{$btr->sviat__novaposhta__state_invalid_action|escape}
                        {elseif $np_key.state == 'denied'}{$btr->sviat__novaposhta__state_denied_action|escape}
                        {elseif $np_key.state == 'denied_old'}{$btr->sviat__novaposhta__state_denied_old_action|escape}
                        {elseif $np_key.state == 'unreachable'}{$btr->sviat__novaposhta__state_unreachable_action|escape}
                        {elseif $np_key.state == 'unchecked'}{$btr->sviat__novaposhta__state_unchecked_action|escape}
                        {else}{$btr->sviat__novaposhta__state_none_action|escape}{/if}
                    </p>
                    {if $np_key.rows}
                        <ul class="np_key_rows">
                            {foreach $np_key.rows as $row}
                                <li>
                                    {if $row.api == 'new_api'}{$btr->sviat__novaposhta__api_new|escape}{else}{$btr->sviat__novaposhta__api_old|escape}{/if}:
                                    <b>
                                        {if $row.access == 'ok'}{$btr->sviat__novaposhta__access_ok|escape}
                                        {elseif $row.access == 'invalid_key'}{$btr->sviat__novaposhta__access_invalid_key|escape}
                                        {elseif $row.access == 'denied'}{$btr->sviat__novaposhta__access_denied|escape}
                                        {else}{$btr->sviat__novaposhta__access_unreachable|escape}{/if}
                                    </b>
                                </li>
                            {/foreach}
                        </ul>
                        <div class="text_grey">{$btr->sviat__novaposhta__checked_at|escape} {$np_key.checked_at|escape}</div>
                    {/if}
                </div>
            </div>
        {/if}

        <div class="np_buttons">
            <button type="submit" class="btn btn_small btn_blue" data-np-busy="{$btr->sviat__novaposhta__checking|escape}">
                <span>{$btr->sviat__novaposhta__save_check|escape}</span>
            </button>
            {if $np_key.has_key}
                <button type="submit" name="recheck" value="1" class="btn btn_small btn-outline-info" data-np-busy="{$btr->sviat__novaposhta__checking|escape}">
                    <span>{$btr->sviat__novaposhta__recheck|escape}</span>
                </button>
            {/if}
        </div>
    </div>
</form>

<form method="post" id="np_dict_form">
    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
    <input type="hidden" name="np_action" value="dictionaries">
    <div class="boxed mb-2">
        <div class="np_box_head">
            <div class="heading_box">{$btr->sviat__novaposhta__dict_box|escape}</div>
            <button type="submit" class="btn btn_small btn-outline-info" {if $np_dict.busy}disabled{/if}>
                <span>{if $np_dict.busy}{$btr->sviat__novaposhta__dict_button_busy|escape}{else}{$btr->sviat__novaposhta__dict_button|escape}{/if}</span>
            </button>
        </div>
        <p class="text_grey">{$btr->sviat__novaposhta__dict_intro|escape}</p>
        {if $np_dict.has_data}
            <div class="np_figures">
                <div><b>{$np_dict.shown.divisions|escape}</b><span>{$btr->sviat__novaposhta__dict_fig_divisions|escape}</span></div>
                <div><b>{$np_dict.shown.postomats|escape}</b><span>{$btr->sviat__novaposhta__dict_fig_postomats|escape}</span></div>
                <div><b>{$np_dict.shown.settlements|escape}</b><span>{$btr->sviat__novaposhta__dict_fig_settlements|escape}</span></div>
                <div><b>{$np_dict.shown.boosted|escape}</b><span>{$btr->sviat__novaposhta__dict_fig_boosted|escape}</span></div>
            </div>
            {if $np_dict.updated_at}
                <div class="text_grey">
                    {$btr->sviat__novaposhta__dict_updated|escape} {$np_dict.updated_at|escape}.
                    {$btr->sviat__novaposhta__dict_changes|replace:'%added%':$np_dict.changes.added|replace:'%changed%':$np_dict.changes.changed|replace:'%deleted%':$np_dict.changes.deleted|escape}
                </div>
            {/if}
        {/if}

        {if $np_dict.state != 'ok'}
            <div class="alert alert--icon mt-1 mb-0 {if $np_dict.state == 'error'}alert--error{elseif $np_dict.state == 'scheduler_idle' || $np_dict.state == 'stopped' || $np_dict.state == 'stale'}alert--warning{else}alert--info{/if}" role="status" aria-live="polite">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $np_dict.state == 'error'}{$btr->sviat__novaposhta__dict_error_title|escape}
                        {elseif $np_dict.state == 'queued'}{$btr->sviat__novaposhta__dict_queued_title|escape}
                        {elseif $np_dict.state == 'running'}{$btr->sviat__novaposhta__dict_running_title|escape}
                        {elseif $np_dict.state == 'scheduler_idle'}{$btr->sviat__novaposhta__dict_idle_title|escape}
                        {elseif $np_dict.state == 'stopped'}{$btr->sviat__novaposhta__dict_stopped_title|escape}
                        {elseif $np_dict.state == 'stale'}{$btr->sviat__novaposhta__dict_stale_title|escape}
                        {else}{$btr->sviat__novaposhta__dict_none_title|escape}{/if}
                    </div>
                    <p>
                        {if $np_dict.state == 'error'}{$btr->sviat__novaposhta__dict_error_text|escape} <span class="text_grey">({$np_dict.error|escape})</span>
                        {elseif $np_dict.state == 'queued'}{$btr->sviat__novaposhta__dict_queued_text|escape}
                        {elseif $np_dict.state == 'running'}{$btr->sviat__novaposhta__dict_running_text|escape}
                        {elseif $np_dict.state == 'scheduler_idle'}{$btr->sviat__novaposhta__dict_idle_text|escape}
                        {elseif $np_dict.state == 'stopped'}{$btr->sviat__novaposhta__dict_stopped_text|escape}
                        {elseif $np_dict.state == 'stale'}{$btr->sviat__novaposhta__dict_stale_text|escape}
                        {else}{$btr->sviat__novaposhta__dict_none_text|escape}{/if}
                    </p>
                    {if $np_dict.state == 'error' || $np_dict.state == 'scheduler_idle' || $np_dict.state == 'stopped' || $np_dict.state == 'stale' || $np_dict.state == 'none'}
                        <p>
                            <b>{$btr->sviat__novaposhta__what_to_do|escape}</b>
                            {if $np_dict.state == 'error'}{$btr->sviat__novaposhta__dict_error_action|escape}
                            {elseif $np_dict.state == 'scheduler_idle' || $np_dict.state == 'stale'}{$btr->sviat__novaposhta__dict_idle_action|escape}
                            {elseif $np_dict.state == 'stopped'}{$btr->sviat__novaposhta__dict_stopped_action|escape}
                            {else}{$btr->sviat__novaposhta__dict_none_action|escape}{/if}
                        </p>
                        {if $np_dict.state == 'scheduler_idle' || $np_dict.state == 'stale'}<code class="np_cron">* * * * * php /path/to/site/ok scheduler:run</code>{/if}
                    {/if}
                </div>
            </div>
        {/if}
        {if $np_dict.boost_warning}
            <div class="alert alert--warning mt-1 mb-0" role="status">
                <div class="alert__content">
                    <p><b>{$btr->sviat__novaposhta__dict_boost_title|escape}</b> {$btr->sviat__novaposhta__dict_boost_text|escape}</p>
                </div>
            </div>
        {/if}
    </div>
</form>

</div>
<div class="col-xl-6 col-lg-12">

<form method="post" id="np_shipping_form">
    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
    <input type="hidden" name="np_action" value="shipping">
    <div class="boxed mb-2">
        <div class="heading_box mb-1">{$btr->sviat__novaposhta__shipping_box|escape}</div>
        <p class="text_grey">{$btr->sviat__novaposhta__shipping_intro|escape}</p>
        {if $np_shipping_saved}
            <div class="alert alert--success" role="status"><div class="alert__content"><p>{$btr->sviat__novaposhta__shipping_saved|escape}</p></div></div>
        {/if}
        {if $np_shipping_errors}
            <div class="alert alert--error" role="alert"><div class="alert__content">
                {foreach $np_shipping_errors as $error}
                    <p>
                        {if $error == 'shipping_error_sender'}{$btr->sviat__novaposhta__shipping_error_sender|escape}
                        {elseif $error == 'shipping_error_weight'}{$btr->sviat__novaposhta__shipping_error_weight|escape}
                        {else}{$btr->sviat__novaposhta__shipping_error_volume|escape}{/if}
                    </p>
                {/foreach}
            </div></div>
        {elseif !$np_shipping.config->senderDivisionId}
            <div class="alert alert--warning" role="status"><div class="alert__content"><p>{$btr->sviat__novaposhta__shipping_no_sender|escape}</p></div></div>
        {/if}
        <div class="np_grid np_grid--point">
            <div>
                <div class="heading_label">{$btr->sviat__novaposhta__shipping_city|escape}</div>
                <div class="np_city" id="np_sender_city_box">
                    <div class="np_city__chosen" {if !$np_shipping.city}hidden{/if}>
                        <span class="np_city__name">{if $np_shipping.city}{if $np_shipping.city.type}<span class="np_city__type">{$np_shipping.city.type|escape}</span> {/if}<b>{$np_shipping.city.title|escape}</b>{/if}</span>
                        <button type="button" class="np_link np_city__change">{$btr->sviat__novaposhta__city_change|escape}</button>
                    </div>
                    <div class="np_city__search np_popular_add" {if $np_shipping.city}hidden{/if}>
                        <input id="np_sender_city" class="form-control" type="text" autocomplete="off" aria-label="{$btr->sviat__novaposhta__shipping_city|escape}" placeholder="{$btr->sviat__novaposhta__popular_add_placeholder|escape}">
                        <ul id="np_sender_suggest" class="np_suggest" role="listbox" hidden></ul>
                    </div>
                </div>
            </div>
            <div>
                <label class="heading_label" for="np_sender_division">{$btr->sviat__novaposhta__shipping_division|escape}</label>
                <select id="np_sender_division" name="sender_division_id" class="selectpicker form-control" data-live-search="true" data-size="10">
                    <option value="">{$btr->sviat__novaposhta__shipping_division_none|escape}</option>
                    {foreach $np_shipping.divisions as $division}
                        <option value="{$division.id|escape}" {if $division.id == $np_shipping.config->senderDivisionId}selected{/if}>{$division.title|escape}</option>
                    {/foreach}
                </select>
            </div>
        </div>
        <div class="np_grid np_grid--two mt-1">
            <div>
                <label class="heading_label" for="np_default_weight">{$btr->sviat__novaposhta__shipping_weight|escape}</label>
                <input id="np_default_weight" class="form-control" type="text" inputmode="decimal" name="default_weight" value="{$np_shipping.config->defaultWeight|escape}">
            </div>
            <div>
                <label class="heading_label" for="np_default_volume">{$btr->sviat__novaposhta__shipping_volume|escape}</label>
                <input id="np_default_volume" class="form-control" type="text" inputmode="decimal" name="default_volume" value="{$np_shipping.config->defaultVolume|escape}">
            </div>
        </div>
        <p class="text_grey mt-1">{$btr->sviat__novaposhta__shipping_defaults_note|escape}</p>
        <div class="np_check mt-1">
            <input class="hidden_check" type="checkbox" id="np_show_term" name="show_term" value="1" {if $np_shipping.config->showTerm}checked{/if}>
            <label class="okay_ckeckbox" for="np_show_term"></label>
            <label class="np_check__text" for="np_show_term"><b>{$btr->sviat__novaposhta__shipping_show_term|escape}</b><br><span class="text_grey">{$btr->sviat__novaposhta__shipping_show_term_note|escape}</span></label>
        </div>
        <div class="np_check mt-1">
            <input class="hidden_check" type="checkbox" id="np_show_lifting" name="show_lifting" value="1" {if $np_shipping.config->showLifting}checked{/if}>
            <label class="okay_ckeckbox" for="np_show_lifting"></label>
            <label class="np_check__text" for="np_show_lifting"><b>{$btr->sviat__novaposhta__shipping_show_lifting|escape}</b><br><span class="text_grey">{$btr->sviat__novaposhta__shipping_show_lifting_note|escape}</span></label>
        </div>
        <div><button type="submit" class="btn btn_small btn_blue mt-1"><span>{$btr->sviat__novaposhta__shipping_save|escape}</span></button></div>
    </div>
</form>

</div>
</div>
</div>

<div id="np_tab_points" class="tab">
<div class="row">
<div class="col-xl-7 col-lg-12">

<form method="post" id="np_groups_form">
    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
    <input type="hidden" name="np_action" value="groups">
    <div class="boxed mb-2">
        <div class="heading_box mb-1">{$btr->sviat__novaposhta__groups_box|escape}</div>
        <p class="text_grey">{$btr->sviat__novaposhta__groups_intro|escape}</p>

        {if $np_groups_saved}
            <div class="alert alert--success" role="status"><div class="alert__content"><p>{$btr->sviat__novaposhta__groups_saved|escape}</p></div></div>
        {/if}
        {if $np_groups_errors}
            <div class="alert alert--error" role="alert"><div class="alert__content">
                {foreach $np_groups_errors as $error}
                    <p>
                        {if $error == 'empty_name'}{$btr->sviat__novaposhta__groups_error_empty_name|escape}
                        {elseif $error == 'category_in_deleted_group'}{$btr->sviat__novaposhta__groups_error_deleted|escape}
                        {else}{$btr->sviat__novaposhta__groups_error_other|escape}{/if}
                    </p>
                {/foreach}
            </div></div>
        {/if}

        <div class="heading_label">{$btr->sviat__novaposhta__groups_list|escape}</div>
        <div id="np_group_rows" class="sortable">
            {foreach $np_groups.groups as $group}
                <div class="np_group_row" data-form-id="{$group.id|escape}">
                    <input type="hidden" data-field="id" value="{$group.id|escape}">
                    <div class="np_drag move_zone" title="{$btr->sviat__novaposhta__groups_drag|escape}">
                        {include file='svg_icon.tpl' svgId='drag_vertical'}
                    </div>
                    <label class="np_group_name">
                        <span class="text_grey">{$btr->sviat__novaposhta__groups_name|escape}</span>
                        <input type="text" class="form-control" data-field="name" value="{$group.name|escape}" maxlength="255">
                    </label>
                    <label class="np_inline">
                        <span>{$btr->sviat__novaposhta__groups_enabled|escape}</span>
                        <span class="switch switch-default">
                            <input class="switch-input" type="checkbox" data-field="enabled" value="1" {if $group.enabled}checked{/if}>
                            <span class="switch-label"></span>
                            <span class="switch-handle"></span>
                        </span>
                    </label>
                    <input type="hidden" data-field="delete" value="">
                    <button type="button" class="btn_close np_popular_remove np_group_remove" aria-label="{$btr->sviat__novaposhta__groups_delete|escape}" title="{$btr->sviat__novaposhta__groups_delete|escape}">{include file='svg_icon.tpl' svgId='trash'}</button>
                </div>
            {/foreach}
            <div class="np_group_row np_group_new" data-form-id="new" hidden>
                <input type="hidden" data-field="id" value="new">
                <div class="np_drag move_zone" title="{$btr->sviat__novaposhta__groups_drag|escape}">
                    {include file='svg_icon.tpl' svgId='drag_vertical'}
                </div>
                <label class="np_group_name">
                    <span class="text_grey">{$btr->sviat__novaposhta__groups_name|escape}</span>
                    <input type="text" class="form-control" data-field="name" value="" maxlength="255">
                </label>
                <label class="np_inline">
                    <span>{$btr->sviat__novaposhta__groups_enabled|escape}</span>
                    <span class="switch switch-default">
                        <input class="switch-input" type="checkbox" data-field="enabled" value="1" checked>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </span>
                </label>
                <button type="button" class="btn_close np_popular_remove np_group_remove" aria-label="{$btr->sviat__novaposhta__groups_delete|escape}" title="{$btr->sviat__novaposhta__groups_delete|escape}">{include file='svg_icon.tpl' svgId='trash'}</button>
            </div>
        </div>
        <button type="button" class="btn btn_mini btn-secondary btn_openSans mt-1 mb-2" id="np_group_add">
            {include file='svg_icon.tpl' svgId='plus'}
            <span>{$btr->sviat__novaposhta__groups_add|escape}</span>
        </button>

        <div class="heading_label">{$btr->sviat__novaposhta__groups_categories|escape}</div>
        {foreach $np_groups.categories as $category}
            <div class="np_category_row">
                <div class="np_category_name">
                    <b>{$btr->getTranslation($category.label_key)|escape}</b>
                    <span class="text_grey">{$category.count|escape} {$btr->sviat__novaposhta__groups_points|escape}</span>
                </div>
                <label class="np_inline">
                    <span>{$btr->sviat__novaposhta__groups_group|escape}</span>
                    <select name="categories[{$category.key|escape}][group]" class="selectpicker form-control np_category_group">
                        <option value="0" {if !$category.group}selected{/if}>{$btr->sviat__novaposhta__groups_none|escape}</option>
                        {foreach $np_groups.groups as $group}
                            <option value="{$group.id|escape}" {if $category.group == $group.id}selected{/if}>{$group.name|escape}</option>
                        {/foreach}
                    </select>
                </label>
                <label class="np_inline">
                    <span>{$btr->sviat__novaposhta__groups_shown|escape}</span>
                    <span class="switch switch-default">
                        <input class="switch-input" type="checkbox" name="categories[{$category.key|escape}][shown]" value="1" {if $category.shown}checked{/if}>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </span>
                </label>
                {if $category.warning}
                    <div class="np_category_warning">
                        {if $category.warning == 'no_group'}{$btr->sviat__novaposhta__groups_warn_no_group|escape}
                        {elseif $category.warning == 'group_disabled'}{$btr->sviat__novaposhta__groups_warn_group_disabled|escape}
                        {else}{$btr->sviat__novaposhta__groups_warn_hidden|escape}{/if}
                    </div>
                {/if}
            </div>
        {/foreach}

        <button type="submit" class="btn btn_small btn_blue mt-1"><span>{$btr->sviat__novaposhta__groups_save|escape}</span></button>
    </div>
</form>

</div>
<div class="col-xl-5 col-lg-12">

<form method="post" id="np_popular_form">
    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
    <input type="hidden" name="np_action" value="popular">
    <div class="boxed mb-2">
        <div class="heading_box mb-1">{$btr->sviat__novaposhta__popular_box|escape}</div>
        <p class="text_grey">{$btr->sviat__novaposhta__popular_intro|escape}</p>
        {if $np_popular_saved}
            <div class="alert alert--success" role="status"><div class="alert__content"><p>{$btr->sviat__novaposhta__popular_saved|escape}</p></div></div>
        {/if}
        <div id="np_popular_rows" class="sortable">
            {foreach $np_popular as $city}
                <div class="np_popular_row">
                    <input type="hidden" name="popular[]" value="{$city.id|escape}">
                    <div class="np_drag move_zone" title="{$btr->sviat__novaposhta__groups_drag|escape}">{include file='svg_icon.tpl' svgId='drag_vertical'}</div>
                    <div class="np_popular_name"><b>{$city.title|escape}</b> <span class="text_grey">{$city.area|escape}</span></div>
                    <button type="button" class="btn_close np_popular_remove" aria-label="{$btr->sviat__novaposhta__popular_remove|escape}" title="{$btr->sviat__novaposhta__popular_remove|escape}">{include file='svg_icon.tpl' svgId='trash'}</button>
                </div>
            {/foreach}
        </div>
        <p class="text_grey np_popular_empty" {if $np_popular}hidden{/if}>{$btr->sviat__novaposhta__popular_empty|escape}</p>
        <div class="np_popular_add">
            <label class="heading_label" for="np_popular_search">{$btr->sviat__novaposhta__popular_add|escape}</label>
            <input id="np_popular_search" class="form-control" type="text" autocomplete="off" placeholder="{$btr->sviat__novaposhta__popular_add_placeholder|escape}">
            <ul id="np_popular_suggest" class="np_suggest" role="listbox" hidden></ul>
        </div>
        <button type="submit" class="btn btn_small btn_blue mt-1"><span>{$btr->sviat__novaposhta__popular_save|escape}</span></button>
    </div>
</form>

</div>
</div>
</div>

<div id="np_tab_waybills" class="tab">
<form method="post" id="np_waybill_form">
<input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
<input type="hidden" name="np_action" value="waybill">
<div class="row">
<div class="col-xl-7 col-lg-12">
    {if $np_waybill_saved}
        <div class="alert alert--success" role="status"><div class="alert__content"><p>{$btr->sviat__novaposhta__shipping_saved|escape}</p></div></div>
    {/if}
    {if $np_waybill_errors}
        <div class="alert alert--error" role="alert"><div class="alert__content">
            {foreach $np_waybill_errors as $error}
                <p>
                    {if $error == 'waybill_error_contact'}{$btr->sviat__novaposhta__waybill_error_contact|escape}
                    {elseif $error == 'waybill_error_phone'}{$btr->sviat__novaposhta__waybill_error_phone|escape}
                    {elseif $error == 'waybill_error_cutoff'}{$btr->sviat__novaposhta__waybill_error_cutoff|escape}
                    {elseif $error == 'waybill_error_description'}{$btr->sviat__novaposhta__waybill_error_description|escape}
                    {elseif $error == 'waybill_error_cargo_types'}{$btr->sviat__novaposhta__waybill_error_cargo_types|escape}
                    {elseif $error == 'waybill_error_cargo_default'}{$btr->sviat__novaposhta__waybill_error_cargo_default|escape}
                    {else}{$btr->sviat__novaposhta__waybill_error_choice|escape}{/if}
                </p>
            {/foreach}
        </div></div>
    {/if}

    <div class="boxed mb-2">
        <div class="heading_box mb-1">{$btr->sviat__novaposhta__waybill_sender_box|escape}</div>
        {if $np_waybill.unavailable}
            <div class="alert alert--warning" role="status"><div class="alert__content"><p>{$btr->sviat__novaposhta__waybill_sender_unavailable|escape}</p></div></div>
        {elseif !$np_waybill.contacts}
            <div class="alert alert--warning" role="status"><div class="alert__content"><p>{$btr->sviat__novaposhta__waybill_no_contact|escape}</p></div></div>
        {/if}
        <div class="np_grid np_grid--two">
            <div>
                <div class="heading_label"><label for="np_waybill_sender">{$btr->sviat__novaposhta__waybill_sender|escape}</label>
                    <i class="fn_tooltips np_tip hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->sviat__novaposhta__waybill_sender_tip|escape}" tabindex="0" role="img" aria-label="{$btr->sviat__novaposhta__waybill_sender_tip|escape}">{include file='svg_icon.tpl' svgId='icon_tooltips'}</i>
                </div>
                <input id="np_waybill_sender" class="form-control" type="text" value="{if $np_waybill.sender}{$np_waybill.sender.name|escape}{/if}" disabled>
            </div>
            <div>
                <label class="heading_label" for="np_waybill_contact">{$btr->sviat__novaposhta__waybill_contact|escape}</label>
                <select id="np_waybill_contact" name="contact_ref" class="selectpicker form-control">
                    {if !$np_waybill.contacts && $np_waybill.config->contactRef}<option value="{$np_waybill.config->contactRef|escape}" selected>{$btr->sviat__novaposhta__waybill_contact_saved|escape}</option>{/if}
                    {foreach $np_waybill.contacts as $contact}
                        <option value="{$contact.ref|escape}" {if $contact.ref == $np_waybill.config->contactRef}selected{/if}>{$contact.name|escape}{if $contact.phone} · {$contact.phone|escape}{/if}</option>
                    {/foreach}
                </select>
            </div>
            <div>
                <div class="heading_label"><label for="np_waybill_phone">{$btr->sviat__novaposhta__waybill_phone|escape}</label>
                    <i class="fn_tooltips np_tip hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->sviat__novaposhta__waybill_phone_tip|escape}" tabindex="0" role="img" aria-label="{$btr->sviat__novaposhta__waybill_phone_tip|escape}">{include file='svg_icon.tpl' svgId='icon_tooltips'}</i>
                </div>
                <input id="np_waybill_phone" class="form-control" type="tel" name="sender_phone" maxlength="20" value="{$np_waybill.config->senderPhone|escape}" placeholder="{$btr->sviat__novaposhta__waybill_phone_placeholder|escape}">
            </div>
            <div>
                <div class="heading_label"><label for="np_waybill_cutoff">{$btr->sviat__novaposhta__waybill_cutoff|escape}</label>
                    <i class="fn_tooltips np_tip hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->sviat__novaposhta__waybill_cutoff_tip|escape}" tabindex="0" role="img" aria-label="{$btr->sviat__novaposhta__waybill_cutoff_tip|escape}">{include file='svg_icon.tpl' svgId='icon_tooltips'}</i>
                </div>
                <select id="np_waybill_cutoff" name="cutoff" class="selectpicker form-control" data-size="8">
                    {foreach $np_waybill.cutoff_choices as $np_slot}
                        <option value="{$np_slot|escape}" {if $np_slot == $np_waybill.config->cutoff}selected{/if}>{$np_slot|escape}</option>
                    {/foreach}
                </select>
            </div>
        </div>
    </div>

    <div class="boxed mb-2">
        <div class="heading_box mb-1">{$btr->sviat__novaposhta__waybill_defaults_box|escape}</div>
        <p class="text_grey">{$btr->sviat__novaposhta__waybill_defaults_intro|escape}</p>
        <div class="heading_label">{$btr->sviat__novaposhta__waybill_cargo_types|escape}</div>
        {* Порожній маркер відрізняє «жодної галочки» від «не надіслано». *}
        <input type="hidden" name="cargo_types[]" value="">
        {foreach ['Parcel', 'Cargo', 'Documents'] as $np_cargo}
            {$np_cargo_key = $np_cargo|lower}
            <div class="np_check mb-h">
                <input class="hidden_check" type="checkbox" id="np_cargo_{$np_cargo_key|escape}" name="cargo_types[]" value="{$np_cargo|escape}" {if in_array($np_cargo, $np_waybill.config->cargoTypes)}checked{/if}>
                <label class="okay_ckeckbox" for="np_cargo_{$np_cargo_key|escape}"></label>
                <label class="np_check__text" for="np_cargo_{$np_cargo_key|escape}"><b>{$btr->getTranslation('sviat__novaposhta__waybill_cargo_'|cat:$np_cargo_key)|escape}</b><br><span class="text_grey">{$btr->getTranslation('sviat__novaposhta__waybill_cargo_'|cat:$np_cargo_key|cat:'_about')|escape}</span></label>
            </div>
        {/foreach}
        <div class="np_grid np_grid--two mt-1">
            <div>
                <div class="heading_label"><label for="np_waybill_payer">{$btr->sviat__novaposhta__waybill_payer|escape}</label>
                    <i class="fn_tooltips np_tip hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->sviat__novaposhta__waybill_payer_tip|escape}" tabindex="0" role="img" aria-label="{$btr->sviat__novaposhta__waybill_payer_tip|escape}">{include file='svg_icon.tpl' svgId='icon_tooltips'}</i>
                </div>
                <select id="np_waybill_payer" name="payer_type" class="selectpicker form-control">
                    <option value="Recipient" {if $np_waybill.config->payerType == 'Recipient'}selected{/if}>{$btr->sviat__novaposhta__waybill_recipient|escape}</option>
                    <option value="Sender" {if $np_waybill.config->payerType == 'Sender'}selected{/if}>{$btr->sviat__novaposhta__waybill_sender_party|escape}</option>
                </select>
            </div>
            <div>
                <label class="heading_label" for="np_waybill_payment">{$btr->sviat__novaposhta__waybill_payment|escape}</label>
                <select id="np_waybill_payment" name="payment_method" class="selectpicker form-control">
                    <option value="Cash" {if $np_waybill.config->paymentMethod == 'Cash'}selected{/if}>{$btr->sviat__novaposhta__waybill_cash|escape}</option>
                    <option value="NonCash" {if $np_waybill.config->paymentMethod == 'NonCash'}selected{/if}>{$btr->sviat__novaposhta__waybill_noncash|escape}</option>
                </select>
            </div>
            <div>
                <div class="heading_label"><label for="np_waybill_cargo">{$btr->sviat__novaposhta__waybill_cargo|escape}</label>
                    <i class="fn_tooltips np_tip hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->sviat__novaposhta__waybill_cargo_tip|escape}" tabindex="0" role="img" aria-label="{$btr->sviat__novaposhta__waybill_cargo_tip|escape}">{include file='svg_icon.tpl' svgId='icon_tooltips'}</i>
                </div>
                <select id="np_waybill_cargo" name="cargo_type" class="selectpicker form-control">
                    <option value="Parcel" {if $np_waybill.config->cargoType == 'Parcel'}selected{/if}>{$btr->sviat__novaposhta__waybill_cargo_parcel|escape}</option>
                    <option value="Cargo" {if $np_waybill.config->cargoType == 'Cargo'}selected{/if}>{$btr->sviat__novaposhta__waybill_cargo_cargo|escape}</option>
                    <option value="Documents" {if $np_waybill.config->cargoType == 'Documents'}selected{/if}>{$btr->sviat__novaposhta__waybill_cargo_documents|escape}</option>
                </select>
            </div>
            <div>
                <div class="heading_label"><label for="np_waybill_cod_payer">{$btr->sviat__novaposhta__waybill_cod_payer|escape}</label>
                    <i class="fn_tooltips np_tip hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->sviat__novaposhta__waybill_cod_payer_tip|escape}" tabindex="0" role="img" aria-label="{$btr->sviat__novaposhta__waybill_cod_payer_tip|escape}">{include file='svg_icon.tpl' svgId='icon_tooltips'}</i>
                </div>
                <select id="np_waybill_cod_payer" name="cod_payer" class="selectpicker form-control">
                    <option value="Recipient" {if $np_waybill.config->codPayer == 'Recipient'}selected{/if}>{$btr->sviat__novaposhta__waybill_recipient|escape}</option>
                    <option value="Sender" {if $np_waybill.config->codPayer == 'Sender'}selected{/if}>{$btr->sviat__novaposhta__waybill_sender_party|escape}</option>
                </select>
            </div>
            <div>
                <div class="heading_label"><label for="np_waybill_description_mode">{$btr->sviat__novaposhta__waybill_description|escape}</label>
                    <i class="fn_tooltips np_tip hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->sviat__novaposhta__waybill_description_tip|escape}" tabindex="0" role="img" aria-label="{$btr->sviat__novaposhta__waybill_description_tip|escape}">{include file='svg_icon.tpl' svgId='icon_tooltips'}</i>
                </div>
                <select id="np_waybill_description_mode" name="description_mode" class="selectpicker form-control">
                    <option value="order" {if $np_waybill.config->descriptionMode == 'order'}selected{/if}>{$btr->sviat__novaposhta__waybill_description_order|escape}</option>
                    <option value="text" {if $np_waybill.config->descriptionMode == 'text'}selected{/if}>{$btr->sviat__novaposhta__waybill_description_text|escape}</option>
                </select>
                <input id="np_waybill_description" class="form-control mt-q" type="text" name="description" maxlength="100" value="{$np_waybill.config->description|escape}" aria-label="{$btr->sviat__novaposhta__waybill_description_text|escape}" {if $np_waybill.config->descriptionMode != 'text'}hidden{/if}>
            </div>
            <div>
                <div class="heading_label"><label for="np_waybill_extra_info">{$btr->sviat__novaposhta__waybill_extra_info|escape}</label>
                    <i class="fn_tooltips np_tip hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->sviat__novaposhta__waybill_extra_info_tip|escape}" tabindex="0" role="img" aria-label="{$btr->sviat__novaposhta__waybill_extra_info_tip|escape}">{include file='svg_icon.tpl' svgId='icon_tooltips'}</i>
                </div>
                <select id="np_waybill_extra_info" name="extra_info" class="selectpicker form-control">
                    <option value="skus" {if $np_waybill.config->extraInfo == 'skus'}selected{/if}>{$btr->sviat__novaposhta__waybill_extra_skus|escape}</option>
                    <option value="names" {if $np_waybill.config->extraInfo == 'names'}selected{/if}>{$btr->sviat__novaposhta__waybill_extra_names|escape}</option>
                    <option value="none" {if $np_waybill.config->extraInfo == 'none'}selected{/if}>{$btr->sviat__novaposhta__waybill_extra_none|escape}</option>
                </select>
            </div>
        </div>
        <div><button type="submit" class="btn btn_small btn_blue mt-1"><span>{$btr->sviat__novaposhta__shipping_save|escape}</span></button></div>
    </div>
</div>
<div class="col-xl-5 col-lg-12">
    <div class="boxed mb-2">
        <div class="heading_box mb-1">{$btr->sviat__novaposhta__cod_box|escape}</div>
        <p class="text_grey">{$btr->sviat__novaposhta__cod_overview_note|escape}</p>
        {foreach $np_cod_payments as $payment}
            <div class="np_cod_row">
                <span>{$payment->name|escape}{if !$payment->enabled} <span class="text_grey">({$btr->sviat__novaposhta__cod_payment_disabled|escape})</span>{/if}</span>
                <span class="text_grey">{if $payment->cod_kind == 'control'}{$btr->sviat__novaposhta__cod_kind_control|escape}{elseif $payment->cod_kind == 'money'}{$btr->sviat__novaposhta__cod_kind_money|escape}{else}{$btr->sviat__novaposhta__cod_kind_none|escape}{/if}</span>
                <a href="index.php?controller=PaymentMethodAdmin&amp;id={$payment->id|escape:'url'}">{$btr->sviat__novaposhta__cod_configure|escape}</a>
            </div>
        {foreachelse}
            <p class="text_grey">{$btr->sviat__novaposhta__cod_no_payments|escape}</p>
        {/foreach}
    </div>
</div>
</div>
</form>
</div>

    </div>
</div>

<template id="np_popular_row_template">
    <div class="np_popular_row">
        <input type="hidden" name="popular[]" value="">
        <div class="np_drag move_zone" title="{$btr->sviat__novaposhta__groups_drag|escape}">{include file='svg_icon.tpl' svgId='drag_vertical'}</div>
        <div class="np_popular_name"><b></b> <span class="text_grey"></span></div>
        <button type="button" class="btn_close np_popular_remove" aria-label="{$btr->sviat__novaposhta__popular_remove|escape}" title="{$btr->sviat__novaposhta__popular_remove|escape}">{include file='svg_icon.tpl' svgId='trash'}</button>
    </div>
</template>

<style>
    .np_readme { display: inline-flex; align-items: center; gap: 8px; }
    .np_tabs > .tab_navigation { margin-bottom: 4px; }
    .np_buttons { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
    .np_status { display: flex; align-items: flex-start; gap: 8px; line-height: 1.45; }
    .np_status svg { flex: none; margin-top: 1px; }
    .np_status--ok { color: #1e7b34; }
    .np_key_rows { margin: 8px 0; padding-left: 18px; }
    .np_key_rows li { margin-bottom: 4px; }
    .np_box_head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 8px; }
    .np_figures { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 10px; }
    .np_figures > div { border: 1px solid var(--ok-line, #e4e6e9); border-radius: 8px; padding: 8px 12px; display: flex; flex-direction: column; }
    .np_figures b { font-size: 18px; font-weight: 600; white-space: nowrap; }
    .np_figures span { font-size: 12px; color: var(--ok-ink-muted, #676e75); line-height: 1.3; }
    @media (max-width: 575px) { .np_figures { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .np_cron { display: block; padding: 6px 10px; margin-top: 6px; overflow-x: auto; white-space: nowrap; }
    .np_group_row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--ok-line, #e4e6e9); }
    .np_group_row[hidden] { display: none; }
    .np_drag { align-self: center; cursor: move; color: var(--ok-ink-muted, #676e75); width: 24px; height: 24px; }
    .np_group_name { flex: 1 1 220px; display: flex; flex-direction: column; gap: 4px; margin: 0; }
    .np_inline { display: inline-flex; align-items: center; gap: 8px; margin: 0; }
    .np_category_row { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--ok-line, #e4e6e9); }
    .np_category_name { flex: 1 1 200px; display: flex; flex-direction: column; }
    .np_category_row .bootstrap-select { min-width: 170px; }
    .np_category_warning { flex-basis: 100%; color: var(--ok-warning, #a35a00); }
    .np_popular_row { display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--ok-line, #e4e6e9); }
    .np_popular_name { flex: 1; min-width: 0; }
    .np_popular_remove { color: var(--ok-danger, #c8352c); }
    .np_popular_remove svg { width: 16px; height: 16px; }
    .np_popular_add { position: relative; margin-top: 12px; }
    .np_suggest { position: absolute; left: 0; right: 0; z-index: 10; margin: 2px 0 0; padding: 4px 0; list-style: none; background: var(--ok-surface, #fff); border: 1px solid var(--ok-control-line, #8a8f95); border-radius: 6px; max-height: 300px; overflow-y: auto; }
    .np_suggest li { padding: 7px 12px; cursor: pointer; line-height: 1.35; }
    .np_suggest li[aria-selected="true"], .np_suggest li:hover { background: var(--ok-sunken, #f3f5f7); }
    .np_suggest__type, .np_city__type { color: var(--ok-ink-muted, #676e75); }
    .np_suggest__where { display: block; font-size: 13px; color: var(--ok-ink-muted, #676e75); }
    .np_city__chosen { display: flex; align-items: center; justify-content: space-between; gap: 10px; box-sizing: border-box; min-height: 35px; padding: 3px 12px; border: 1px solid var(--ok-control-line, #ced4da); border-radius: 4px; }
    .np_city__chosen[hidden], .np_city__search[hidden] { display: none; }
    .np_city__search.np_popular_add { margin-top: 0; }
    .np_link { background: none; border: 0; padding: 0; color: var(--ok-accent, #135c94); cursor: pointer; text-decoration: underline; font: inherit; }
    /* На стоку немає широких підказок: довгі тексти переносяться, а не виходять за екран. */
    .np_tip[data-hint]:after { width: max-content; max-width: 280px; white-space: normal; font-style: normal; font-weight: 400; font-size: 13px; line-height: 1.35; text-align: left; padding: 8px 10px; color: #23272b; background: #fff; border: 1px solid #d0d5da; border-radius: 6px; box-shadow: 0 4px 12px rgba(0, 0, 0, .08); text-shadow: none; }
    .np_grid { display: grid; gap: 12px; align-items: end; }
    .np_grid > div { min-width: 0; }
    /* Підпис з іконкою підказки і звичайний стоять на одній лінії. */
    .np_grid .heading_label { display: flex; align-items: center; gap: 4px; min-height: 22px; margin: 0 0 6px; }
    .np_grid .heading_label label { margin: 0; }
    .np_grid--point { grid-template-columns: minmax(0, 2fr) minmax(0, 3fr); }
    .np_grid--two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .np_grid .bootstrap-select { width: 100% !important; }
    @media (max-width: 767px) { .np_grid--point, .np_grid--two { grid-template-columns: minmax(0, 1fr); } }
    .np_check { display: flex; align-items: flex-start; gap: 8px; }
    .np_check .okay_ckeckbox { flex: none; margin: 0; }
    .np_check__text { font-weight: 400; cursor: pointer; margin: 0; line-height: 1.4; }
    .np_cod_row { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 12px; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--ok-line, #e4e6e9); }
    @media (max-width: 575px) { .np_buttons .btn { width: 100%; } .np_cod_row { grid-template-columns: minmax(0, 1fr) auto; } .np_cod_row > .text_grey { grid-column: 1; } }
</style>

<script>
    document.getElementById('np_key_form').addEventListener('submit', function (event) {
        var pressed = event.submitter;
        this.querySelectorAll('button[type=submit]').forEach(function (button) {
            button.disabled = true;
        });
        if (pressed && pressed.dataset.npBusy) {
            pressed.querySelector('span').textContent = pressed.dataset.npBusy;
            // Вимкнена кнопка не надсилається: прапорець повторної перевірки тримаємо у формі.
            if (pressed.name) {
                var flag = document.createElement('input');
                flag.type = 'hidden';
                flag.name = pressed.name;
                flag.value = pressed.value;
                this.appendChild(flag);
            }
        }
    });

    /** Одна підказка населеного пункту: тип звичайним текстом, назва жирним, район і область нижче. */
    function npSettlementItem(city) {
        var item = document.createElement('li');
        item.setAttribute('role', 'option');
        if (city.type) {
            var type = document.createElement('span');
            type.className = 'np_suggest__type';
            type.textContent = city.type + ' ';
            item.appendChild(type);
        }
        var name = document.createElement('b');
        name.textContent = city.title || city.name;
        item.appendChild(name);
        var where = [city.district, city.area].filter(Boolean).join(', ');
        if (where) {
            var line = document.createElement('span');
            line.className = 'np_suggest__where';
            line.textContent = where;
            item.appendChild(line);
        }

        return item;
    }

    (function () {
        var mode = document.getElementById('np_waybill_description_mode');
        var text = document.getElementById('np_waybill_description');
        $(mode).on('changed.bs.select change', function () {
            text.hidden = mode.value !== 'text';
            if (!text.hidden) {
                text.focus();
            }
        });
    })();

    (function () {
        var form = document.getElementById('np_groups_form');
        var rows = document.getElementById('np_group_rows');
        var newRow = rows.querySelector('.np_group_new');
        var newName = newRow.querySelector('[data-field="name"]');

        var selects = $(form).find('select.np_category_group');

        document.getElementById('np_group_add').addEventListener('click', function () {
            newRow.hidden = false;
            this.hidden = true;
            selects.append($('<option>', { value: 'new' }).text(newName.value.trim()));
            selects.selectpicker('refresh');
            newName.focus();
        });

        newName.addEventListener('input', function () {
            selects.find('option[value="new"]').text(newName.value.trim());
            selects.selectpicker('refresh');
        });

        // Видалення спрацьовує після «Зберегти»; до того сторінку можна перезавантажити, і група повернеться.
        rows.addEventListener('click', function (event) {
            var button = event.target.closest('.np_group_remove');
            if (!button) {
                return;
            }
            var row = button.closest('.np_group_row');
            row.hidden = true;
            if (row === newRow) {
                newName.value = '';
                selects.find('option[value="new"]').remove();
                selects.selectpicker('refresh');
                document.getElementById('np_group_add').hidden = false;
                return;
            }
            row.classList.add('np_group_row--deleted');
            row.querySelector('[data-field="delete"]').value = '1';
        });

        // Атрибути name йдуть у порядку на сторінці, тож перетягнута група зберігає нову позицію.
        form.addEventListener('submit', function () {
            var index = 0;
            rows.querySelectorAll('.np_group_row').forEach(function (row) {
                if (row.hidden && !row.classList.contains('np_group_row--deleted')) {
                    return;
                }
                row.querySelectorAll('[data-field]').forEach(function (input) {
                    input.name = 'groups[' + index + '][' + input.dataset.field + ']';
                });
                index++;
            });
        });
    })();

    /** Пошук населеного пункту в полі з debounce; onPick отримує обраний населений пункт. */
    function npSettlementSearch(input, list, onPick) {
        var url = '{$smarty.server.SCRIPT_NAME|escape:'javascript'}?controller=Sviat.NovaPoshta.NovaPoshtaSettingsAdmin@searchSettlements&q=';
        var timer = null;
        var request = null;

        function close() {
            list.hidden = true;
            list.textContent = '';
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var query = input.value.trim();
            if (query === '') {
                close();
                return;
            }
            timer = setTimeout(function () {
                if (request) {
                    request.abort();
                }
                request = new AbortController();
                fetch(url + encodeURIComponent(query), { credentials: 'same-origin', signal: request.signal })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        list.textContent = '';
                        (data.settlements || []).forEach(function (city) {
                            var item = npSettlementItem(city);
                            item.addEventListener('mousedown', function (e) {
                                e.preventDefault();
                                onPick(city);
                                close();
                            });
                            list.appendChild(item);
                        });
                        list.hidden = list.children.length === 0;
                    })
                    .catch(function () {});
            }, 200);
        });
        input.addEventListener('blur', function () { setTimeout(close, 150); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var first = list.querySelector('li');
                if (first) {
                    first.dispatchEvent(new Event('mousedown'));
                }
            } else if (e.key === 'Escape') {
                close();
            }
        });
    }

    (function () {
        var rows = document.getElementById('np_popular_rows');
        var empty = document.querySelector('.np_popular_empty');
        var input = document.getElementById('np_popular_search');
        var template = document.getElementById('np_popular_row_template');

        function refreshEmpty() {
            empty.hidden = rows.querySelector('.np_popular_row') !== null;
        }

        rows.addEventListener('click', function (event) {
            var button = event.target.closest('.np_popular_remove');
            if (button) {
                button.closest('.np_popular_row').remove();
                refreshEmpty();
            }
        });

        npSettlementSearch(input, document.getElementById('np_popular_suggest'), function (city) {
            input.value = '';
            if (rows.querySelector('input[value="' + city.id + '"]')) {
                return;
            }
            var row = template.content.firstElementChild.cloneNode(true);
            row.querySelector('input').value = city.id;
            row.querySelector('b').textContent = city.title || city.name;
            row.querySelector('.text_grey').textContent = city.area || '';
            rows.appendChild(row);
            refreshEmpty();
        });
    })();

    (function () {
        var box = document.getElementById('np_sender_city_box');
        var chosen = box.querySelector('.np_city__chosen');
        var search = box.querySelector('.np_city__search');
        var input = document.getElementById('np_sender_city');
        var select = document.getElementById('np_sender_division');
        var base = '{$smarty.server.SCRIPT_NAME|escape:'javascript'}?controller=Sviat.NovaPoshta.NovaPoshtaSettingsAdmin@';

        box.querySelector('.np_city__change').addEventListener('click', function () {
            chosen.hidden = true;
            search.hidden = false;
            input.value = '';
            input.focus();
        });

        npSettlementSearch(input, document.getElementById('np_sender_suggest'), function (city) {
            var name = chosen.querySelector('.np_city__name');
            name.textContent = '';
            if (city.type) {
                var type = document.createElement('span');
                type.className = 'np_city__type';
                type.textContent = city.type + ' ';
                name.appendChild(type);
            }
            var title = document.createElement('b');
            title.textContent = city.title || city.name;
            name.appendChild(title);
            chosen.hidden = false;
            search.hidden = true;
            fetch(base + 'senderDivisions&settlement_id=' + encodeURIComponent(city.id), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    while (select.options.length > 1) {
                        select.remove(1);
                    }
                    (data.divisions || []).forEach(function (division) {
                        var option = document.createElement('option');
                        option.value = division.id;
                        option.textContent = division.title;
                        select.appendChild(option);
                    });
                    select.value = '';
                    if (window.jQuery && jQuery.fn.selectpicker) {
                        jQuery(select).selectpicker('refresh');
                    }
                })
                .catch(function () {});
        });
    })();
</script>
