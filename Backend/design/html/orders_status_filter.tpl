{if $np_status_groups}
    <div class="col-md-3 col-lg-3 col-sm-12">
        <select class="selectpicker form-control" onchange="location = this.value;" aria-label="{$btr->sviat__novaposhta__status_filter|escape}">
            <option value="{url np_waybill=null page=null}" {if !$np_status_group}selected{/if}>{$btr->sviat__novaposhta__status_filter_all|escape}</option>
            {foreach $np_status_groups as $group}
                <option value="{url np_waybill=$group page=null}" {if $np_status_group == $group}selected{/if}>{$btr->getTranslation("sviat__novaposhta__status_group_`$group`")|escape}</option>
            {/foreach}
        </select>
    </div>
{/if}
