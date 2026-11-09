{if $order->np_waybill}
    {$nw = $order->np_waybill}
    {* As the old tracking module showed it: logo, number (a click copies it), a small badge; the full NP status is in its hint. *}
    <div class="np-list-status">
        <svg class="np-list-status__logo" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path fill="#f23c32" fill-rule="evenodd" d="M1.70703 11.293C1.31641 11.6835 1.31641 12.3167 1.70703 12.7072L5.99988 17.0001V14.0001V10.0001V7.00009L1.70703 11.293ZM6.99988 6.00009H9.99988V10.0001H13.9999V6.00009H16.9999L12.707 1.70718C12.3164 1.31668 11.6833 1.31668 11.2927 1.70718L6.99988 6.00009ZM17.9999 7.00009V10.0001V14.0001V17.0001L22.2927 12.7072C22.6833 12.3167 22.6833 11.6835 22.2927 11.293L17.9999 7.00009ZM16.9999 18.0001H13.9999V14.0001H9.99988V18.0001H6.99988L11.2927 22.293C11.6833 22.6835 12.3164 22.6835 12.707 22.293L16.9999 18.0001Z"/></svg>
        <a href="" class="np-list-status__number fn_clipboard hint-bottom-middle-t-info-s-small-mobile hint-anim" data-hint="{$btr->sviat__novaposhta__list_copy|escape}" data-hint-copied="{$btr->sviat__novaposhta__list_copied|escape}" data-copy-string="{$nw.number|escape}">{$nw.numberSpaced|escape}</a>
        {if $nw.statusKey}<span class="np-wb__badge np-wb__badge--{$nw.group|escape}{if $nw.statusText} hint-bottom-middle-t-info-s-small-mobile hint-anim{/if}"{if $nw.statusText} data-hint="{$nw.statusText|escape}"{/if}>{$btr->getTranslation($nw.statusKey)|escape}</span>{/if}
    </div>
{/if}
