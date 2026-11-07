{if $order->np_waybill}
    {$nw = $order->np_waybill}
    <div class="np-list-status">
        <span class="np-list-status__number" title="{$btr->sviat__novaposhta__waybill_title|escape}">{$nw.numberSpaced|escape}</span>
        {if $nw.statusKey}<span class="np-wb__badge np-wb__badge--{$nw.group|escape}"{if $nw.statusText} title="{$nw.statusText|escape}"{/if}>{$btr->getTranslation($nw.statusKey)|escape}</span>{/if}
    </div>
{/if}
