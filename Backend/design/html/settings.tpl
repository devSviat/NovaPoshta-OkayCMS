{$meta_title = $btr->sviat__novaposhta__title scope=global}

<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="heading_page">{$btr->sviat__novaposhta__title|escape}</div>
    </div>
</div>

<form method="post" id="np_key_form">
    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">

    <div class="row">
        <div class="col-lg-8 col-md-12">
            <div class="boxed mb-2">
                <div class="heading_box mb-1">{$btr->sviat__novaposhta__key_box|escape}</div>
                <p>{$btr->sviat__novaposhta__key_intro|escape}</p>

                <div class="form-group">
                    <label class="heading_label" for="np_api_key">{$btr->sviat__novaposhta__key_label|escape}</label>
                    <input id="np_api_key" name="api_key" class="form-control" type="text" value=""
                           autocomplete="off" spellcheck="false"
                           placeholder="{if $np_key.has_key}{$btr->sviat__novaposhta__key_placeholder_saved|escape}{else}{$btr->sviat__novaposhta__key_placeholder_new|escape}{/if}">
                    {if $np_key.has_key}
                        <div class="text_grey mt-q">
                            {$btr->sviat__novaposhta__key_saved_prefix|escape} <b>{$np_key.key_masked|escape}</b>. {$btr->sviat__novaposhta__key_saved_suffix|escape}
                        </div>
                    {/if}
                </div>

                <div class="np_key_buttons">
                    <button type="submit" class="btn btn_small btn_blue" data-np-busy="{$btr->sviat__novaposhta__checking|escape}">
                        <span>{$btr->sviat__novaposhta__save_check|escape}</span>
                    </button>
                    {if $np_key.has_key}
                        <button type="submit" name="recheck" value="1" class="btn btn_small btn-secondary" data-np-busy="{$btr->sviat__novaposhta__checking|escape}">
                            <span>{$btr->sviat__novaposhta__recheck|escape}</span>
                        </button>
                    {/if}
                </div>
            </div>
        </div>
    </div>
</form>

<div class="row">
    <div class="col-lg-8 col-md-12">
        <div class="alert alert--icon {if $np_key.state == 'ok'}alert--success{elseif $np_key.state == 'invalid'}alert--error{elseif $np_key.state == 'denied' || $np_key.state == 'denied_old' || $np_key.state == 'unreachable'}alert--warning{else}alert--info{/if}" role="status" aria-live="polite">
            <div class="alert__content">
                <div class="alert__title">
                    {if $np_key.state == 'ok'}{$btr->sviat__novaposhta__state_ok_title|escape}
                    {elseif $np_key.state == 'invalid'}{$btr->sviat__novaposhta__state_invalid_title|escape}
                    {elseif $np_key.state == 'denied'}{$btr->sviat__novaposhta__state_denied_title|escape}
                    {elseif $np_key.state == 'denied_old'}{$btr->sviat__novaposhta__state_denied_old_title|escape}
                    {elseif $np_key.state == 'unreachable'}{$btr->sviat__novaposhta__state_unreachable_title|escape}
                    {elseif $np_key.state == 'unchecked'}{$btr->sviat__novaposhta__state_unchecked_title|escape}
                    {else}{$btr->sviat__novaposhta__state_none_title|escape}{/if}
                </div>
                <p>
                    {if $np_key.state == 'ok'}{$btr->sviat__novaposhta__state_ok_text|escape}
                    {elseif $np_key.state == 'invalid'}{$btr->sviat__novaposhta__state_invalid_text|escape}
                    {elseif $np_key.state == 'denied'}{$btr->sviat__novaposhta__state_denied_text|escape}
                    {elseif $np_key.state == 'denied_old'}{$btr->sviat__novaposhta__state_denied_old_text|escape}
                    {elseif $np_key.state == 'unreachable'}{$btr->sviat__novaposhta__state_unreachable_text|escape}
                    {elseif $np_key.state == 'unchecked'}{$btr->sviat__novaposhta__state_unchecked_text|escape}
                    {else}{$btr->sviat__novaposhta__state_none_text|escape}{/if}
                    {if $np_key.just_saved} {$btr->sviat__novaposhta__saved|escape}{/if}
                    {if $np_key.kept_previous} {$btr->sviat__novaposhta__not_saved_kept|escape}
                    {elseif $np_key.rejected} {$btr->sviat__novaposhta__not_saved|escape}{/if}
                </p>
                {if $np_key.state != 'ok'}
                    <p>
                        <b>{$btr->sviat__novaposhta__what_to_do|escape}</b>
                        {if $np_key.state == 'invalid'}{$btr->sviat__novaposhta__state_invalid_action|escape}
                        {elseif $np_key.state == 'denied'}{$btr->sviat__novaposhta__state_denied_action|escape}
                    {elseif $np_key.state == 'denied_old'}{$btr->sviat__novaposhta__state_denied_old_action|escape}
                        {elseif $np_key.state == 'unreachable'}{$btr->sviat__novaposhta__state_unreachable_action|escape}
                        {elseif $np_key.state == 'unchecked'}{$btr->sviat__novaposhta__state_unchecked_action|escape}
                        {else}{$btr->sviat__novaposhta__state_none_action|escape}{/if}
                    </p>
                {/if}
                {if $np_key.rows}
                    <ul class="np_key_rows">
                        {foreach $np_key.rows as $row}
                            <li>
                                <span>{if $row.api == 'new_api'}{$btr->sviat__novaposhta__api_new|escape}{else}{$btr->sviat__novaposhta__api_old|escape}{/if}</span>
                                —
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
    </div>
</div>

{* Dictionaries *}
<form method="post" id="np_dict_form">
    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
    <input type="hidden" name="np_action" value="dictionaries">
    <div class="row">
        <div class="col-lg-8 col-md-12">
            <div class="boxed mb-2">
                <div class="np_box_head">
                    <div class="heading_box">{$btr->sviat__novaposhta__dict_box|escape}</div>
                    <button type="submit" class="btn btn_small btn_blue" {if $np_dict.busy}disabled{/if}>
                        <span>{if $np_dict.busy}{$btr->sviat__novaposhta__dict_button_busy|escape}{else}{$btr->sviat__novaposhta__dict_button|escape}{/if}</span>
                    </button>
                </div>
                <p>{$btr->sviat__novaposhta__dict_intro|escape}</p>
                {if $np_dict.has_data}
                    <div class="np_figures">
                        <div><b>{$np_dict.figures.divisions|escape}</b><span>{$btr->sviat__novaposhta__dict_fig_divisions|escape}</span></div>
                        <div><b>{$np_dict.figures.postomats|escape}</b><span>{$btr->sviat__novaposhta__dict_fig_postomats|escape}</span></div>
                        <div><b>{$np_dict.figures.settlements|escape}</b><span>{$btr->sviat__novaposhta__dict_fig_settlements|escape}</span></div>
                        <div><b>{$np_dict.figures.boosted|escape}</b><span>{$btr->sviat__novaposhta__dict_fig_boosted|escape}</span></div>
                    </div>
                    {if $np_dict.updated_at}
                        <div class="text_grey">
                            {$btr->sviat__novaposhta__dict_updated|escape} <b>{$np_dict.updated_at|escape}</b> ·
                            {$btr->sviat__novaposhta__dict_changes|escape}
                            +{$np_dict.changes.added|escape} / ~{$np_dict.changes.changed|escape} / −{$np_dict.changes.deleted|escape}
                        </div>
                    {/if}
                {/if}
            </div>
        </div>
    </div>
</form>

<div class="row">
    <div class="col-lg-8 col-md-12">
        <div class="alert alert--icon {if $np_dict.state == 'ok'}alert--success{elseif $np_dict.state == 'error'}alert--error{elseif $np_dict.state == 'scheduler_idle' || $np_dict.state == 'stopped' || $np_dict.state == 'stale'}alert--warning{else}alert--info{/if}" role="status" aria-live="polite">
            <div class="alert__content">
                <div class="alert__title">
                    {if $np_dict.state == 'ok'}{$btr->sviat__novaposhta__dict_ok_title|escape}
                    {elseif $np_dict.state == 'error'}{$btr->sviat__novaposhta__dict_error_title|escape}
                    {elseif $np_dict.state == 'queued'}{$btr->sviat__novaposhta__dict_queued_title|escape}
                    {elseif $np_dict.state == 'running'}{$btr->sviat__novaposhta__dict_running_title|escape}
                    {elseif $np_dict.state == 'scheduler_idle'}{$btr->sviat__novaposhta__dict_idle_title|escape}
                    {elseif $np_dict.state == 'stopped'}{$btr->sviat__novaposhta__dict_stopped_title|escape}
                    {elseif $np_dict.state == 'stale'}{$btr->sviat__novaposhta__dict_stale_title|escape}
                    {else}{$btr->sviat__novaposhta__dict_none_title|escape}{/if}
                </div>
                <p>
                    {if $np_dict.state == 'ok'}{$btr->sviat__novaposhta__dict_ok_text|escape}
                    {elseif $np_dict.state == 'error'}{$btr->sviat__novaposhta__dict_error_text|escape} <span class="text_grey">({$np_dict.error|escape})</span>
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
        {if $np_dict.boost_warning}
            <div class="alert alert--warning" role="status">
                <div class="alert__content">
                    <p><b>{$btr->sviat__novaposhta__dict_boost_title|escape}</b> {$btr->sviat__novaposhta__dict_boost_text|escape}</p>
                </div>
            </div>
        {/if}
    </div>
</div>

{* Groups *}
<form method="post" id="np_groups_form">
    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
    <input type="hidden" name="np_action" value="groups">
    <div class="row">
        <div class="col-lg-8 col-md-12">
            <div class="boxed mb-2">
                <div class="heading_box mb-1">{$btr->sviat__novaposhta__groups_box|escape}</div>
                <p>{$btr->sviat__novaposhta__groups_intro|escape}</p>

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
                            <label class="np_inline text_grey">
                                <input type="checkbox" data-field="delete" value="1"> {$btr->sviat__novaposhta__groups_delete|escape}
                            </label>
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
                    </div>
                </div>
                <button type="button" class="btn btn_mini btn-secondary btn_openSans mb-2" id="np_group_add">
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
                                <option value="0" {if !$category.group}selected{/if}>— {$btr->sviat__novaposhta__groups_none|escape}</option>
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
        </div>
    </div>
</form>

{* Sender and calculation *}
<form method="post" id="np_shipping_form">
    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
    <input type="hidden" name="np_action" value="shipping">
    <div class="row">
        <div class="col-lg-8 col-md-12">
            <div class="boxed mb-2">
                <div class="heading_box mb-1">{$btr->sviat__novaposhta__shipping_box|escape}</div>
                <p>{$btr->sviat__novaposhta__shipping_intro|escape}</p>
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
                <div class="np_shipping_grid">
                    <div class="np_popular_add">
                        <label class="heading_label" for="np_sender_city">{$btr->sviat__novaposhta__shipping_city|escape}</label>
                        <input id="np_sender_city" class="form-control" type="text" autocomplete="off" value="{if $np_shipping.city}{$np_shipping.city.name|escape}{/if}" placeholder="{$btr->sviat__novaposhta__popular_add_placeholder|escape}">
                        <ul id="np_sender_suggest" class="np_suggest" role="listbox" hidden></ul>
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
                <label class="np_check mt-1"><input type="checkbox" name="show_term" value="1" {if $np_shipping.config->showTerm}checked{/if}> <span><b>{$btr->sviat__novaposhta__shipping_show_term|escape}</b><br><span class="text_grey">{$btr->sviat__novaposhta__shipping_show_term_note|escape}</span></span></label>
                <label class="np_check mt-1"><input type="checkbox" name="show_lifting" value="1" {if $np_shipping.config->showLifting}checked{/if}> <span><b>{$btr->sviat__novaposhta__shipping_show_lifting|escape}</b><br><span class="text_grey">{$btr->sviat__novaposhta__shipping_show_lifting_note|escape}</span></span></label>
                <div><button type="submit" class="btn btn_small btn_blue mt-1"><span>{$btr->sviat__novaposhta__shipping_save|escape}</span></button></div>
            </div>
        </div>
    </div>
</form>

{* Cash on delivery *}
<form method="post" id="np_cod_form">
    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
    <input type="hidden" name="np_action" value="cod">
    <div class="row">
        <div class="col-lg-8 col-md-12">
            <div class="boxed mb-2">
                <div class="heading_box mb-1">{$btr->sviat__novaposhta__cod_box|escape}</div>
                {if $np_cod_saved}
                    <div class="alert alert--success" role="status"><div class="alert__content"><p>{$btr->sviat__novaposhta__shipping_saved|escape}</p></div></div>
                {/if}
                <fieldset class="np_fieldset">
                    <legend class="heading_label">{$btr->sviat__novaposhta__cod_kind|escape}</legend>
                    <label class="np_check"><input type="radio" name="cod_kind" value="money" {if $np_cod.kind == 'money'}checked{/if}> <span><b>{$btr->sviat__novaposhta__cod_kind_money|escape}</b><br><span class="text_grey">{$btr->sviat__novaposhta__cod_kind_money_note|escape}</span></span></label>
                    <label class="np_check"><input type="radio" name="cod_kind" value="control" {if $np_cod.kind == 'control'}checked{/if}> <span><b>{$btr->sviat__novaposhta__cod_kind_control|escape}</b><br><span class="text_grey">{$btr->sviat__novaposhta__cod_kind_control_note|escape}</span></span></label>
                </fieldset>
                <div class="heading_label mt-1">{$btr->sviat__novaposhta__cod_payments|escape}</div>
                <p class="text_grey">{$btr->sviat__novaposhta__cod_payments_note|escape}</p>
                {foreach $np_cod.payments as $payment}
                    <label class="np_check np_payment_row"><input type="checkbox" name="cod_payments[]" value="{$payment->id|escape}" {if $payment->cod}checked{/if}> <span>{$payment->name|escape}{if !$payment->enabled} <span class="text_grey">({$btr->sviat__novaposhta__cod_payment_disabled|escape})</span>{/if}</span></label>
                {foreachelse}
                    <p class="text_grey">{$btr->sviat__novaposhta__cod_no_payments|escape}</p>
                {/foreach}
                <div><button type="submit" class="btn btn_small btn_blue mt-1"><span>{$btr->sviat__novaposhta__shipping_save|escape}</span></button></div>
            </div>
        </div>
    </div>
</form>

{* Popular cities *}
<form method="post" id="np_popular_form">
    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
    <input type="hidden" name="np_action" value="popular">
    <div class="row">
        <div class="col-lg-8 col-md-12">
            <div class="boxed mb-2">
                <div class="heading_box mb-1">{$btr->sviat__novaposhta__popular_box|escape}</div>
                <p>{$btr->sviat__novaposhta__popular_intro|escape}</p>
                {if $np_popular_saved}
                    <div class="alert alert--success" role="status"><div class="alert__content"><p>{$btr->sviat__novaposhta__popular_saved|escape}</p></div></div>
                {/if}
                <div id="np_popular_rows" class="sortable">
                    {foreach $np_popular as $city}
                        <div class="np_popular_row">
                            <input type="hidden" name="popular[]" value="{$city.id|escape}">
                            <div class="np_drag move_zone" title="{$btr->sviat__novaposhta__groups_drag|escape}">{include file='svg_icon.tpl' svgId='drag_vertical'}</div>
                            <div class="np_popular_name"><b>{$city.name|escape}</b> <span class="text_grey">{$city.area|escape}</span></div>
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
        </div>
    </div>
</form>

<template id="np_popular_row_template">
    <div class="np_popular_row">
        <input type="hidden" name="popular[]" value="">
        <div class="np_drag move_zone" title="{$btr->sviat__novaposhta__groups_drag|escape}">{include file='svg_icon.tpl' svgId='drag_vertical'}</div>
        <div class="np_popular_name"><b></b> <span class="text_grey"></span></div>
        <button type="button" class="btn_close np_popular_remove" aria-label="{$btr->sviat__novaposhta__popular_remove|escape}" title="{$btr->sviat__novaposhta__popular_remove|escape}">{include file='svg_icon.tpl' svgId='trash'}</button>
    </div>
</template>

<style>
    .np_key_buttons { display: flex; flex-wrap: wrap; gap: 10px; }
    .np_key_rows { margin: 8px 0; padding-left: 18px; }
    .np_key_rows li { margin-bottom: 4px; }
    .np_box_head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 8px; }
    .np_figures { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 10px; }
    .np_figures > div { border: 1px solid var(--ok-line, #e4e6e9); border-radius: 6px; padding: 8px 12px; display: flex; flex-direction: column; }
    .np_figures b { font-size: 18px; }
    .np_cron { display: block; padding: 6px 10px; margin-top: 6px; overflow-x: auto; white-space: nowrap; }
    .np_group_row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--ok-line, #e4e6e9); }
    .np_group_row[hidden] { display: none; }
    .np_drag { align-self: center; cursor: move; color: var(--ok-ink-muted, #676e75); width: 24px; height: 24px; }
    .np_group_name { flex: 1 1 220px; display: flex; flex-direction: column; gap: 4px; margin: 0; }
    .np_inline { display: inline-flex; align-items: center; gap: 8px; margin: 0; }
    .np_category_row { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--ok-line, #e4e6e9); }
    .np_category_name { flex: 1 1 220px; display: flex; flex-direction: column; }
    .np_category_row .bootstrap-select { min-width: 180px; }
    .np_category_warning { flex-basis: 100%; color: var(--ok-warning, #a35a00); }
    .np_popular_row { display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--ok-line, #e4e6e9); }
    .np_popular_name { flex: 1; min-width: 0; }
    .np_popular_remove { color: var(--ok-danger, #c8352c); }
    .np_popular_remove svg { width: 16px; height: 16px; }
    .np_popular_add { position: relative; margin-top: 12px; }
    .np_suggest { position: absolute; left: 0; right: 0; z-index: 10; margin: 2px 0 0; padding: 4px 0; list-style: none; background: var(--ok-surface, #fff); border: 1px solid var(--ok-control-line, #8a8f95); border-radius: 6px; max-height: 280px; overflow-y: auto; }
    .np_suggest li { padding: 8px 12px; cursor: pointer; }
    .np_suggest li[aria-selected="true"], .np_suggest li:hover { background: var(--ok-sunken, #fafbfc); }
    .np_shipping_grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
    .np_shipping_grid .bootstrap-select { width: 100% !important; }
    .np_shipping_grid .np_popular_add { margin-top: 0; }
    .np_fieldset { border: 0; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
    .np_payment_row { padding: 8px 0; border-bottom: 1px solid var(--ok-line, #e4e6e9); margin: 0; }
    .np_check { display: flex; align-items: flex-start; gap: 8px; }
    .np_check input { margin-top: 4px; }
    @media (max-width: 575px) { .np_key_buttons .btn, #np_dict_form .btn { width: 100%; } }
</style>

<script>
    document.getElementById('np_key_form').addEventListener('submit', function (event) {
        var pressed = event.submitter;
        this.querySelectorAll('button[type=submit]').forEach(function (button) {
            button.disabled = true;
        });
        if (pressed && pressed.dataset.npBusy) {
            pressed.querySelector('span').textContent = pressed.dataset.npBusy;
            // A disabled button is not submitted: keep the recheck flag in the form.
            if (pressed.name) {
                var flag = document.createElement('input');
                flag.type = 'hidden';
                flag.name = pressed.name;
                flag.value = pressed.value;
                this.appendChild(flag);
            }
        }
    });

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

        // Names follow the order on the page, so a dragged group keeps its new position.
        form.addEventListener('submit', function () {
            var index = 0;
            rows.querySelectorAll('.np_group_row').forEach(function (row) {
                if (row.hidden) {
                    return;
                }
                row.querySelectorAll('[data-field]').forEach(function (input) {
                    input.name = 'groups[' + index + '][' + input.dataset.field + ']';
                });
                index++;
            });
        });
    })();

    (function () {
        var rows = document.getElementById('np_popular_rows');
        var empty = document.querySelector('.np_popular_empty');
        var input = document.getElementById('np_popular_search');
        var list = document.getElementById('np_popular_suggest');
        var template = document.getElementById('np_popular_row_template');
        var url = '{$smarty.server.SCRIPT_NAME|escape:'javascript'}?controller=Sviat.NovaPoshta.NovaPoshtaSettingsAdmin@searchSettlements&q=';
        var timer = null;
        var request = null;

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

        function add(city) {
            if (rows.querySelector('input[value="' + city.id + '"]')) {
                return;
            }
            var row = template.content.firstElementChild.cloneNode(true);
            row.querySelector('input').value = city.id;
            row.querySelector('b').textContent = city.name;
            row.querySelector('.text_grey').textContent = city.area || '';
            rows.appendChild(row);
            refreshEmpty();
        }

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
                            var item = document.createElement('li');
                            item.setAttribute('role', 'option');
                            var name = document.createElement('b');
                            name.textContent = city.name;
                            item.appendChild(name);
                            item.appendChild(document.createTextNode(' ' + [city.district, city.area].filter(Boolean).join(', ')));
                            item.addEventListener('mousedown', function (e) {
                                e.preventDefault();
                                add(city);
                                input.value = '';
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
    })();
    (function () {
        var input = document.getElementById('np_sender_city');
        var list = document.getElementById('np_sender_suggest');
        var select = document.getElementById('np_sender_division');
        var base = '{$smarty.server.SCRIPT_NAME|escape:'javascript'}?controller=Sviat.NovaPoshta.NovaPoshtaSettingsAdmin@';
        var timer = null;
        var request = null;

        function close() {
            list.hidden = true;
            list.textContent = '';
        }

        function choose(city) {
            input.value = city.name;
            close();
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
                fetch(base + 'searchSettlements&q=' + encodeURIComponent(query), { credentials: 'same-origin', signal: request.signal })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        list.textContent = '';
                        (data.settlements || []).forEach(function (city) {
                            var item = document.createElement('li');
                            item.setAttribute('role', 'option');
                            var name = document.createElement('b');
                            name.textContent = city.name;
                            item.appendChild(name);
                            item.appendChild(document.createTextNode(' ' + [city.district, city.area].filter(Boolean).join(', ')));
                            item.addEventListener('mousedown', function (e) {
                                e.preventDefault();
                                choose(city);
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
    })();
</script>
