{* Перенесення зі старих модулів Нової Пошти: одноразова дія. *}
{if $np_legacy}
{$L = $np_legacy}
<div class="row">
    <div class="col-12">
        <div class="boxed mb-2 np_legacy">
            <div class="heading_box mb-1">{$btr->sviat__novaposhta__legacy_box|escape}</div>

            {if $L.problem}
                <div class="alert alert--warning" role="alert"><div class="alert__content"><p>{$btr->getTranslation('sviat__novaposhta__'|cat:$L.problem)|escape}</p></div></div>
            {/if}
            {if $L.stalled}
                <div class="alert alert--warning" role="alert"><div class="alert__content"><p>{$btr->sviat__novaposhta__legacy_stalled|escape}</p></div></div>
            {/if}

            {if $L.phase == 'before'}
                <p>{$btr->sviat__novaposhta__legacy_found|replace:'%orders%':$L.preview.orders|replace:'%waybills%':$L.preview.waybills|escape}</p>
                <p class="text_grey">{$btr->sviat__novaposhta__legacy_copy_note|escape}</p>
            {elseif $L.phase == 'running'}
                <p><b>{$btr->sviat__novaposhta__legacy_running|escape} {$L.stepNumber|escape} / {$L.stepCount|escape}{if $L.currentStep}: {$btr->getTranslation('sviat__novaposhta__legacy_row_'|cat:$L.currentStep)|escape}{/if}</b></p>
                <p class="text_grey">{$btr->sviat__novaposhta__legacy_running_note|escape} <a href="">{$btr->sviat__novaposhta__legacy_refresh|escape}</a></p>
            {else}
                <p><b class="text_success">✓ {$btr->sviat__novaposhta__legacy_done|replace:'%date%':$L.finishedAt|escape}</b> {$btr->sviat__novaposhta__legacy_done_counts|replace:'%orders%':$L.orders|replace:'%waybills%':$L.waybills|escape}</p>
                {if $L.failed}
                    <p class="text_grey">{$btr->sviat__novaposhta__legacy_not_moved|replace:'%count%':$L.failed|escape}</p>
                {/if}
                <p>
                    {if $L.switch}{$btr->sviat__novaposhta__legacy_next|escape}
                    {foreach $L.switch as $d}<a href="index.php?controller=DeliveryAdmin&amp;id={$d.id|escape:'url'}">{$d.name|escape}</a>{if !$d@last}, {/if}{/foreach}. {/if}{$btr->sviat__novaposhta__legacy_next_modules|escape}
                    <a href="index.php?controller=ModulesAdmin">{$btr->sviat__novaposhta__legacy_modules_link|escape}</a>.
                </p>
            {/if}

            {if $L.canStart}
                <form method="post" class="mt-1">
                    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                    <input type="hidden" name="np_action" value="legacy_migrate">
                    {if $L.phase == 'done'}
                        <button type="submit" class="btn btn_small btn-outline-info"><span>{$btr->sviat__novaposhta__legacy_again|escape}</span></button>
                        <span class="text_grey">{$btr->sviat__novaposhta__legacy_again_note|escape}</span>
                    {else}
                        <button type="submit" class="btn btn_small btn_blue"><span>{$btr->sviat__novaposhta__legacy_start|escape}</span></button>
                    {/if}
                </form>
            {/if}
        </div>
    </div>
</div>
{/if}
