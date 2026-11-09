{* Making the waybill (form, check, refusal): in the right column beside the order's delivery data. *}
{if $np_waybill && !$np_waybill.row}{include file=$smarty.current_dir|cat:'/waybill.tpl'}{/if}
