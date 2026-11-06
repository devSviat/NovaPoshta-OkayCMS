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

<style>
    .np_key_buttons { display: flex; flex-wrap: wrap; gap: 10px; }
    .np_key_rows { margin: 8px 0; padding-left: 18px; }
    .np_key_rows li { margin-bottom: 4px; }
    @media (max-width: 575px) { .np_key_buttons .btn { width: 100%; } }
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
</script>
