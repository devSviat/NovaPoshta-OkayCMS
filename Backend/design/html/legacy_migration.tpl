{* Moving from the old Nova Poshta modules (stage 5, mockup Migration.dc.html) *}
{if $np_legacy}
{$L = $np_legacy}
<div class="row">
    <div class="col-lg-8 col-md-12">
        <div class="boxed mb-2 np_legacy">
            <div class="heading_box mb-1">{$btr->sviat__novaposhta__legacy_box|escape}</div>
            {if $L.enabled}
                <p class="text_grey">{$btr->sviat__novaposhta__legacy_enabled|escape} {foreach $L.enabled as $m}{$m|escape}{if !$m@last}, {/if}{/foreach}. {$btr->sviat__novaposhta__legacy_still_work|escape}</p>
            {/if}
            <ol class="np_legacy__steps">
                <li class="np_legacy__done">{$btr->sviat__novaposhta__legacy_step1|escape} ✓</li>
                <li class="{if $L.phase == 'done'}np_legacy__done{else}np_legacy__now{/if}">{$btr->sviat__novaposhta__legacy_step2|escape}{if $L.phase == 'done'} ✓{/if}</li>
                <li{if $L.phase == 'done'} class="np_legacy__now"{/if}>{$btr->sviat__novaposhta__legacy_step3|escape}</li>
                <li>{$btr->sviat__novaposhta__legacy_step4|escape}</li>
                <li>{$btr->sviat__novaposhta__legacy_step5|escape}</li>
            </ol>

            {if $L.problem}
                <div class="alert alert--warning" role="alert"><div class="alert__content"><p>{$btr->getTranslation('sviat__novaposhta__'|cat:$L.problem)|escape}</p></div></div>
            {/if}
            {if $L.stalled}
                <div class="alert alert--warning" role="alert"><div class="alert__content"><p>{$btr->sviat__novaposhta__legacy_stalled|escape}</p></div></div>
            {/if}
            {if $np_legacy_backup_missing}
                <div class="alert alert--warning" role="alert"><div class="alert__content"><p>{$btr->sviat__novaposhta__legacy_backup_missing|escape}</p></div></div>
            {/if}

            {if $L.phase == 'before'}
                <div class="heading_label">{$btr->sviat__novaposhta__legacy_found|escape}</div>
                <div class="np_legacy__table">
                    <span class="np_legacy__th">{$btr->sviat__novaposhta__legacy_col_data|escape}</span><span class="np_legacy__th np_legacy__num">{$btr->sviat__novaposhta__legacy_col_found|escape}</span>
                    {foreach $L.preview as $step => $n}
                        <span>{$btr->getTranslation('sviat__novaposhta__legacy_row_'|cat:$step)|escape}</span><span class="np_legacy__num">{$n|escape}</span>
                    {/foreach}
                </div>
            {/if}

            {if $L.phase == 'running'}
                <p><b>{$btr->sviat__novaposhta__legacy_running|escape} {$L.stepNumber|escape} / {$L.stepCount|escape}{if $L.currentStep}: {$btr->getTranslation('sviat__novaposhta__legacy_row_'|cat:$L.currentStep)|escape}{/if}</b></p>
                <p class="text_grey">{$btr->sviat__novaposhta__legacy_running_note|escape}</p>
                <a class="btn btn_small btn-outline-primary" href="">{$btr->sviat__novaposhta__legacy_refresh|escape}</a>
            {/if}

            {if $L.phase == 'done'}
                <div class="alert alert--success" role="status"><div class="alert__content"><p>{$btr->sviat__novaposhta__legacy_done|escape} {$L.finishedAt|escape}</p></div></div>
                <div class="np_legacy__table np_legacy__table--report">
                    <span class="np_legacy__th">{$btr->sviat__novaposhta__legacy_col_data|escape}</span>
                    <span class="np_legacy__th np_legacy__num">{$btr->sviat__novaposhta__legacy_col_done|escape}</span>
                    <span class="np_legacy__th np_legacy__num">{$btr->sviat__novaposhta__legacy_col_existed|escape}</span>
                    <span class="np_legacy__th np_legacy__num">{$btr->sviat__novaposhta__legacy_col_failed|escape}</span>
                    {foreach $L.rows as $step => $c}
                        <span>{$btr->getTranslation('sviat__novaposhta__legacy_row_'|cat:$step)|escape}</span>
                        <span class="np_legacy__num np_legacy__ok">{$c.done|escape}</span>
                        <span class="np_legacy__num np_legacy__skip">{$c.existed|escape}</span>
                        <span class="np_legacy__num np_legacy__fail">{$c.failed|escape}</span>
                    {/foreach}
                </div>
                {if $L.fails}
                    <div class="heading_label mt-1">{$btr->sviat__novaposhta__legacy_fails_title|escape}</div>
                    <div class="np_legacy__fails">
                        {foreach $L.fails as $f}
                            <div>
                                <span>{if $f.order}<a href="index.php?controller=OrderAdmin&amp;id={$f.order|escape:'url'}">№ {$f.order|escape}</a>{elseif $f.record}{$btr->sviat__novaposhta__legacy_record|escape} № {$f.record|escape}{else}{$btr->getTranslation('sviat__novaposhta__'|cat:'legacy_row_'|cat:$f.step)|escape}{/if}</span>
                                <span>{if $f.key == 'legacy_fail_no_order'}{$btr->sviat__novaposhta__legacy_fail_no_order|replace:'%s':$f.name|escape}{else}{if $f.name}<b>{$f.name|escape}</b> — {/if}{$btr->getTranslation('sviat__novaposhta__'|cat:$f.key)|escape}{/if}</span>
                            </div>
                        {/foreach}
                        {if $L.moreFails}<div><span></span><span class="text_grey">{$btr->sviat__novaposhta__legacy_more|escape} {$L.moreFails|escape}</span></div>{/if}
                    </div>
                {/if}
                <p class="text_grey mt-1">{$btr->sviat__novaposhta__legacy_again_note|escape}</p>
            {/if}

            {if $L.canStart}
                <form method="post">
                    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                    <input type="hidden" name="np_action" value="legacy_migrate">
                    {if $L.phase == 'before'}
                        <div class="alert alert--warning mt-1" role="note"><div class="alert__content"><p>{$btr->sviat__novaposhta__legacy_backup_warning|escape}</p></div></div>
                    {/if}
                    <div class="np_check mt-1">
                        <input class="hidden_check" type="checkbox" id="np_legacy_backup" name="legacy_backup" value="1">
                        <label class="okay_ckeckbox" for="np_legacy_backup"></label>
                        <label class="np_check__text" for="np_legacy_backup">{$btr->sviat__novaposhta__legacy_backup_check|escape}</label>
                    </div>
                    <button type="submit" class="btn btn_small btn_blue mt-1"><span>{if $L.phase == 'done'}{$btr->sviat__novaposhta__legacy_again|escape}{else}{$btr->sviat__novaposhta__legacy_start|escape}{/if}</span></button>
                </form>
            {/if}
        </div>

        {if $L.switch}
            <div class="boxed mb-2">
                <div class="heading_box mb-1">{$btr->sviat__novaposhta__legacy_switch_title|escape}</div>
                <p class="text_grey">{$btr->sviat__novaposhta__legacy_switch_note|escape}</p>
                <div class="np_legacy__fails">
                    {foreach $L.switch as $d}
                        <div>
                            <span><a href="index.php?controller=DeliveryAdmin&amp;id={$d.id|escape:'url'}">{$d.name|escape}</a></span>
                            <span>{if $d.mode == 'points'}{$btr->sviat__novaposhta__legacy_switch_points|escape}{elseif $d.mode == 'address'}{$btr->sviat__novaposhta__legacy_switch_address|escape}{else}{$btr->sviat__novaposhta__legacy_switch_unknown|escape}{/if}</span>
                        </div>
                    {/foreach}
                </div>
            </div>
        {/if}
    </div>
</div>
{/if}
